/**
 * SIPRO - Sistem Informasi Penjualan Properti
 * Interaktivitas JavaScript (UX, Currency Formatter, Photo Preview, Auto-scroll)
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Enter to send pesan pada chat WhatsApp & Auto-resize textarea sejajar
    const chatTextarea = document.querySelector('.chat-input-bar textarea');
    if (chatTextarea) {
        chatTextarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() !== '') {
                    this.form.submit();
                }
            }
        });
        chatTextarea.addEventListener('input', function () {
            this.style.height = '44px';
            const newHeight = Math.min(this.scrollHeight, 120);
            if (newHeight > 44) {
                this.style.height = newHeight + 'px';
                this.style.borderRadius = '14px';
            } else {
                this.style.borderRadius = '22px';
            }
        });
    }

    // 2. Format Rupiah Live Preview pada input harga
    function formatRupiahString(val) {
        let number_string = val.replace(/[^,\d]/g, '').toString();
        let split = number_string.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
        return rupiah ? 'Rp ' + rupiah : '';
    }

    const priceInputs = document.querySelectorAll(
        'input[type="number"][name*="harga"], input[name="harga_usulan"], input[name="harga"], input[name="min"], input[name="max"], .format-rupiah'
    );

    priceInputs.forEach(input => {
        const badge = document.createElement('div');
        badge.className = 'rupiah-live-badge';
        badge.style.display = 'none';
        input.parentNode.appendChild(badge);

        function updatePreview() {
            const val = input.value.trim();
            if (val && !isNaN(val) && Number(val) > 0) {
                badge.textContent = 'Format: ' + formatRupiahString(val);
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        input.addEventListener('input', updatePreview);
        input.addEventListener('focus', updatePreview);
        if (input.value) updatePreview();
    });

    // 3. Image Upload Preview di Form Properti Admin
    const fotoInput = document.querySelector('input[type="file"][name="foto[]"]');
    if (fotoInput) {
        let previewWrap = document.getElementById('foto-preview');
        if (!previewWrap) {
            previewWrap = document.createElement('div');
            previewWrap.id = 'foto-preview';
            previewWrap.className = 'foto-preview-grid';
            fotoInput.parentNode.appendChild(previewWrap);
        }

        fotoInput.addEventListener('change', function () {
            previewWrap.innerHTML = '';
            if (!this.files || !this.files.length) return;

            Array.from(this.files).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'foto-preview-item';
                    img.title = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                    previewWrap.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    // 4. Flash Message Auto-Dismiss & Tombol Tutup
    document.querySelectorAll('.msg').forEach(msg => {
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.className = 'msg-close';
        closeBtn.title = 'Tutup pesan';
        closeBtn.onclick = function () {
            msg.style.display = 'none';
        };
        msg.prepend(closeBtn);

        setTimeout(() => {
            msg.style.transition = 'opacity 0.6s ease';
            msg.style.opacity = '0';
            setTimeout(() => { msg.style.display = 'none'; }, 600);
        }, 6000);
    });

    // Inisialisasi toggle jenis usulan jika elemen ada di halaman
    if (document.getElementById('jenis')) {
        toggleProposal();
    }
});

// 5. Global Toggle Jenis Usulan (Jadwal vs Harga)
window.toggleProposal = function () {
    const sel = document.getElementById('jenis');
    if (!sel) return;
    const v = sel.value;
    const dateField = document.getElementById('datefield');
    const priceField = document.getElementById('pricefield');

    if (dateField) {
        dateField.hidden = (v !== 'Jadwal');
        const input = dateField.querySelector('input');
        if (input) input.required = (v === 'Jadwal');
    }
    if (priceField) {
        priceField.hidden = (v !== 'Harga');
        const input = priceField.querySelector('input');
        if (input) input.required = (v === 'Harga');
    }
};
