var WEB_API_URL = "https://domain-anda.com/api-sheets-sync.php"; 

function doPost(e) {
  try {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    var data = JSON.parse(e.postData.contents);
    
    var rowData = [
      data.id || '',
      data.date || '',
      data.customer || '',
      data.type || '',
      data.status || 'Selesai',
      data.reporterName || '',
      data.reporterNik || '',
      data.technician1Name || '',
      data.technician1Nik || '',
      data.technician2Name || '',
      data.technician2Nik || ''
    ];

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
  var row = range.getRow();

  if (row <= 1) return;

  var workOrder = sheet.getRange(row, 1).getValue();
  if (!workOrder || workOrder === 'Work Order / ID') return;

  if (range.getColumn() === 1 && (e.value === undefined || e.value === "")) {
    sendSyncToWeb("DELETE", workOrder);
  } else if (range.getColumn() === 3 || range.getColumn() === 5) {
    var customerName = sheet.getRange(row, 3).getValue();
    var status = sheet.getRange(row, 5).getValue();
    sendSyncToWeb("UPDATE", workOrder, customerName, status);
  }
}

function sendSyncToWeb(actionType, workOrderId, customerName, status) {
  if (!WEB_API_URL || WEB_API_URL.indexOf("localhost") !== -1) {
    Logger.log("WEB_API_URL localhost");
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
