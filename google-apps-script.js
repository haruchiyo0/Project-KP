var WEB_API_URL = "http://localhost/api-sheets-sync.php"; // Ganti jika sudah online

function doPost(e) {
  try {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("Jobs");
    if (!sheet) sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    var data = JSON.parse(e.postData.contents);
    
    var rowData = [
      data.id || '',
      data.date || '',
      data.customer || '',
      data.type || '',
      data.status || 'Selesai',
      data.technician1Name || '',
      data.technician1Nik || '',
      data.technician2Name || '',
      data.technician2Nik || '',
      data.incomePerTech || 0
    ];

    if (data.action === 'update') {
      var oldId = data.oldId || data.id;
      var lastRow = sheet.getLastRow();
      var targetRow = -1;
      
      // Search for the row with matching oldId in Column A
      if (lastRow >= 2) {
        var ids = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
        for (var i = 0; i < ids.length; i++) {
          if (ids[i][0] == oldId) {
            targetRow = i + 2;
            break;
          }
        }
      }
      
      if (targetRow !== -1) {
        sheet.getRange(targetRow, 1, 1, rowData.length).setValues([rowData]);
        return ContentService.createTextOutput(JSON.stringify({ 
          "status": "success", 
          "message": "Data berhasil diupdate di Baris " + targetRow
        })).setMimeType(ContentService.MimeType.JSON);
      } else {
        return ContentService.createTextOutput(JSON.stringify({ 
          "status": "error", 
          "message": "Data lama tidak ditemukan untuk diupdate"
        })).setMimeType(ContentService.MimeType.JSON);
      }
    } else if (data.action === 'delete') {
      var targetId = data.id;
      var lastRow = sheet.getLastRow();
      
      if (lastRow >= 2) {
        var ids = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
        for (var i = 0; i < ids.length; i++) {
          if (ids[i][0] == targetId) {
            sheet.deleteRow(i + 2);
            return ContentService.createTextOutput(JSON.stringify({
              "status": "success",
              "message": "Pekerjaan " + targetId + " berhasil dihapus dari Google Sheets"
            })).setMimeType(ContentService.MimeType.JSON);
          }
        }
      }
      return ContentService.createTextOutput(JSON.stringify({ 
        "status": "error", 
        "message": "Data pekerjaan tidak ditemukan di Google Sheets"
      })).setMimeType(ContentService.MimeType.JSON);
    } else {
      // Append new row
      var targetRow = 2;
      var lastRow = sheet.getLastRow();
      
      if (lastRow >= 2) {
        for (var r = 2; r <= lastRow + 1; r++) {
          var cellVal = sheet.getRange(r, 1).getValue();
          if (cellVal === "" || cellVal === null) {
            targetRow = r;
            break;
          }
        }
      }

      sheet.getRange(targetRow, 1, 1, rowData.length).setValues([rowData]);

      return ContentService.createTextOutput(JSON.stringify({ 
        "status": "success", 
        "message": "Data berhasil disimpan di Baris " + targetRow
      })).setMimeType(ContentService.MimeType.JSON);
    }
  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({ 
      "status": "error", 
      "message": error.toString() 
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

// Menambahkan fungsi doGet untuk membaca Users
function doGet(e) {
  try {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("Users");
    if (!sheet) {
      return ContentService.createTextOutput(JSON.stringify({
        "status": "error",
        "message": "Sheet 'Users' tidak ditemukan"
      })).setMimeType(ContentService.MimeType.JSON);
    }

    var data = sheet.getDataRange().getValues();
    var users = [];
    
    // Asumsi baris 1 adalah header: Name, NIK, Username, Password, Role
    for (var i = 1; i < data.length; i++) {
      var row = data[i];
      if (row[2]) { // Username tidak boleh kosong
        users.push({
          "name": row[0] ? row[0].toString() : "",
          "nik": row[1] ? row[1].toString() : "",
          "username": row[2] ? row[2].toString() : "",
          "password": row[3] ? row[3].toString() : "",
          "role": row[4] ? row[4].toString().trim().toLowerCase() : "teknisi",
          "status": row[5] ? row[5].toString().trim().toLowerCase() : "active"
        });
      }
    }

    return ContentService.createTextOutput(JSON.stringify({
      "status": "success",
      "data": users
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({ 
      "status": "error", 
      "message": error.toString() 
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

function onEdit(e) {
  if (!e || !e.range) return;

  var range = e.range;
  var sheet = range.getSheet();
  if (sheet.getName() === "Users") {
    var userRow = range.getRow();
    if (userRow <= 1) return;
    
    // Check if column F (Status) was edited
    if (range.getColumn() === 6) {
      var username = sheet.getRange(userRow, 3).getValue();
      var status = sheet.getRange(userRow, 6).getValue();
      
      if (username) {
        sendSyncUserToWeb(username, status);
      }
    }
    return;
  }

  if (sheet.getName() !== "Jobs") return; // Hanya trigger di sheet Jobs

  var row = range.getRow();
  if (row <= 1) return;

  // Deteksi Hapus Data (Clear cell Kolom A)
  if (range.getColumn() === 1 && (!e.value || e.value === "")) {
      var deletedWO = e.oldValue;
      if (deletedWO) {
          sendSyncToWeb("DELETE", deletedWO);
      }
      return;
  }

  var workOrder = sheet.getRange(row, 1).getValue();
  if (!workOrder || workOrder === 'Work Order / ID') return;

  if (range.getColumn() === 3 || range.getColumn() === 5) {
    var customerName = sheet.getRange(row, 3).getValue();
    var status = sheet.getRange(row, 5).getValue();
    sendSyncToWeb("UPDATE", workOrder, customerName, status);
  }
}

function sendSyncUserToWeb(username, status) {
  if (!WEB_API_URL || WEB_API_URL.indexOf("localhost") !== -1) {
    Logger.log("WEB_API_URL localhost, melewati sync...");
    return;
  }

  var payload = {
    "action": "UPDATE_USER",
    "username": username,
    "status": status || 'active'
  };

  var options = {
    "method": "post",
    "contentType": "application/json",
    "payload": JSON.stringify(payload),
    "muteHttpExceptions": true
  };

  try {
    UrlFetchApp.fetch(WEB_API_URL, options);
  } catch (err) {
    Logger.log(err.toString());
  }
}

function sendSyncToWeb(actionType, workOrderId, customerName, status) {
  if (!WEB_API_URL || WEB_API_URL.indexOf("localhost") !== -1) {
    Logger.log("WEB_API_URL localhost, melewati sync...");
    return; // Abaikan jika masih localhost
  }

  var payload = {
    "action": actionType,
    "work_order": workOrderId,
    "customer_name": customerName || '',
    "status": status || 'Selesai'
  };

  var options = {
    "method": "post",
    "contentType": "application/json",
    "payload": JSON.stringify(payload),
    "muteHttpExceptions": true
  };

  try {
    var response = UrlFetchApp.fetch(WEB_API_URL, options);
    Logger.log(response.getContentText());
  } catch (err) {
    Logger.log(err.toString());
  }
}
