<?php
// Shared Modal HTML for Job Details
?>
<!-- Job Details Modal Overlay -->
<div id="jobDetailModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.4); z-index: 9999; backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease;">
    <div class="modal-content" style="background: #ffffff; border-radius: 20px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transform: translateY(20px); transition: transform 0.3s ease; position: relative;">
        
        <!-- Header -->
        <div class="modal-header" style="padding: 24px 24px 16px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="margin: 0; font-size: 12px; font-weight: 600; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">Detail Pekerjaan</p>
                <h3 id="modalWo" style="margin: 4px 0 0 0; font-size: 1.5rem; font-weight: 700; color: #0f172a;">-</h3>
            </div>
            <button id="closeJobModal" style="background: #f1f5f9; border: none; color: #64748b; font-size: 20px; cursor: pointer; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">&times;</button>
        </div>

        <div class="modal-body" style="padding: 24px;">
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 24px; align-items: center;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-size: 12px; color: #64748b;">Tanggal PS</span>
                    <strong id="modalDate" style="color: #334155; font-size: 1rem;">-</strong>
                </div>
                <div style="text-align: right; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                    <span style="font-size: 12px; color: #64748b;">Jenis Layanan</span>
                    <span id="modalType" class="type-badge" style="display: inline-block;">-</span>
                </div>
            </div>

            <!-- Pelanggan Card -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Pelanggan</div>
                        <strong id="modalCustomer" style="color: #0f172a; font-size: 1.05rem;">-</strong>
                    </div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center; padding-top: 12px; border-top: 1px dashed #cbd5e1;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                    <span style="font-size: 13px; color: #64748b;">No Internet:</span>
                    <strong id="modalInet" style="color: #334155; font-size: 13px;">-</strong>
                </div>
            </div>

            <!-- Detail Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        Pelapor
                    </div>
                    <strong id="modalReporter" style="color: #334155; font-size: 0.9rem;">-</strong>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        Tim Teknisi
                    </div>
                    <div id="modalTechnicians" style="color: #334155; font-size: 0.9rem; line-height: 1.5;">-</div>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Keterangan / Kendala
                </div>
                <div id="modalDesc" style="color: #475569; font-size: 0.95rem; line-height: 1.6; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 12px; min-height: 80px;">-</div>
            </div>

            <!-- Keuangan -->
            <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 12px; padding: 20px; border: 1px solid #bbf7d0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 12px; color: #166534; margin-bottom: 4px; font-weight: 600;">Nilai Pekerjaan (Base)</div>
                        <strong id="modalBase" style="font-size: 1.5rem; color: #15803d;">-</strong>
                    </div>
                    <?php if ($user['role'] === 'teknisi'): ?>
                    <div style="text-align: right; border-left: 1px dashed #86efac; padding-left: 20px;">
                        <div style="font-size: 12px; color: #166534; margin-bottom: 4px; font-weight: 600;">Bagian Anda</div>
                        <strong id="modalShare" style="font-size: 1.5rem; color: #059669;">-</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        
        <div class="modal-footer" id="modalFooterActions" style="padding: 16px 24px; border-top: 1px solid #f1f5f9; background: #f8fafc; text-align: right; display: none; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;">
            <!-- Tombol Edit di-inject via JS jika user teknisi & pembuat -->
        </div>
    </div>
</div>

<style>
/* Hover effect for clickable rows/cards */
.job-row:hover { background: rgba(255,255,255,0.03); }
.job-card { transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
.job-card:hover { border-color: rgba(56, 189, 248, 0.4); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('jobDetailModal');
    const modalContent = modal.querySelector('.modal-content');
    const closeBtn = document.getElementById('closeJobModal');
    
    if (!modal) return; // Prevent error if modal isn't properly included

    // Elements to fill
    const elWo = document.getElementById('modalWo');
    const elDate = document.getElementById('modalDate');
    const elType = document.getElementById('modalType');
    const elCustomer = document.getElementById('modalCustomer');
    const elInet = document.getElementById('modalInet');
    const elReporter = document.getElementById('modalReporter');
    const elTechs = document.getElementById('modalTechnicians');
    const elDesc = document.getElementById('modalDesc');
    const elBase = document.getElementById('modalBase');
    const elShare = document.getElementById('modalShare');
    const elFooter = document.getElementById('modalFooterActions');
    
    // User info for edit button logic
    const currentUserRole = '<?= $user['role'] ?>';
    const currentUserId = <?= (int) $user['id'] ?>;

    function formatRupiah(num) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
    }

    function openModal(data) {
        // Populate data
        elWo.textContent = data.work_order || '-';
        
        // Format date to local standard if possible, else direct
        try {
            const d = new Date(data.ps_date);
            elDate.textContent = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        } catch(e) {
            elDate.textContent = data.ps_date || '-';
        }

        elType.textContent = data.work_type || '-';
        elType.className = 'type-badge type-' + (data.work_type || '').toLowerCase();
        elCustomer.textContent = data.customer_name || '-';
        elInet.textContent = data.no_inet || '-';
        elReporter.textContent = (data.reporter_name || '-') + ' (' + (data.reporter_nik || '-') + ')';
        
        if (data.technicians) {
            elTechs.innerHTML = data.technicians.split(' | ').join('<br>');
        } else {
            elTechs.textContent = '-';
        }
        
        elDesc.textContent = data.description || '-';
        elBase.textContent = formatRupiah(data.base_amount || 0);
        
        if (elShare && data.share_amount !== undefined) {
            elShare.textContent = formatRupiah(data.share_amount);
        }

        // Show edit button if technician and is creator
        if (currentUserRole === 'teknisi' && parseInt(data.created_by) === currentUserId) {
            elFooter.style.display = 'block';
            elFooter.innerHTML = '<a href="job-edit.php?id=' + data.id + '" class="primary-button" style="display: inline-block;">Edit Pekerjaan</a>';
        } else {
            elFooter.style.display = 'none';
        }

        // Show Modal
        modal.style.display = 'flex';
        // Trigger reflow
        void modal.offsetWidth;
        modal.style.opacity = '1';
        modalContent.style.transform = 'translateY(0)';
    }

    function closeModal() {
        modal.style.opacity = '0';
        modalContent.style.transform = 'translateY(20px)';
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    // Make rows and cards clickable
    const clickableItems = document.querySelectorAll('.job-row, .job-card');
    clickableItems.forEach(item => {
        item.style.cursor = 'pointer';
        item.addEventListener('click', function(e) {
            // Jika mengklik link (misal tombol edit di tabel), jangan buka modal
            if (e.target.tagName.toLowerCase() === 'a' || e.target.closest('a')) return;
            const dataStr = this.getAttribute('data-json');
            if (dataStr) {
                try {
                    const data = JSON.parse(dataStr);
                    openModal(data);
                } catch(err) {
                    console.error('Error parsing job data', err);
                }
            }
        });
    });
});
</script>
