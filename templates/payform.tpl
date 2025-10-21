<div style="max-width:600px;margin:40px auto;padding:20px;border:1px solid #ddd;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,0.1);">
    <h3 class="text-center" style="background:#0d6efd;color:#fff;padding:10px;border-radius:6px;">
        Pay via UPI (BharatPe)
    </h3>

    <div style="text-align:center;margin-top:20px;">
        <p><strong>Invoice ID:</strong> #{$invoiceid}</p>
        <p><strong>Amount:</strong> ₹{$amount}</p>

        <img src="{$systemurl}/modules/gateways/upi_bharatpe/includes/qr_generator.php?invoiceid={$invoiceid}&amount={$amount}" 
             alt="UPI QR Code" 
             style="width:200px;height:200px;border:1px solid #ccc;border-radius:10px;padding:5px;">

        <p style="margin-top:15px;">
            <strong>UPI ID:</strong> <span style="color:#007bff;">BHARATPE.9T0X0P0W1N905507@unitype</span>
        </p>

        <p style="margin-top:10px;">After completing payment, enter your UTR/Transaction ID below:</p>

        <input type="text" id="utrInput" placeholder="Enter UTR / Transaction ID"
               style="width:80%;padding:8px;text-align:center;border:1px solid #ccc;border-radius:5px;">

        <br>
        <button id="submitUtrBtn" style="margin-top:15px;background:#28a745;color:#fff;border:none;padding:10px 25px;border-radius:5px;">
            Submit UTR
        </button>

        <div id="utrMessage" style="margin-top:15px;display:none;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('submitUtrBtn');
    const utrInput = document.getElementById('utrInput');
    const msgBox = document.getElementById('utrMessage');

    btn.addEventListener('click', function() {
        const utr = utrInput.value.trim();

        if (!utr) {
            msgBox.style.display = 'block';
            msgBox.innerHTML = '<span style="color:red;">⚠️ Please enter your UTR/Transaction ID.</span>';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Submitting... ⏳';
        msgBox.style.display = 'none';

        fetch('{$systemurl}/modules/gateways/upi_bharatpe/callback/verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'invoiceid={$invoiceid}&utr=' + encodeURIComponent(utr)
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Submit UTR';
            msgBox.style.display = 'block';

            if (data.status === 'success') {
                msgBox.innerHTML = '<span style="color:green;font-weight:bold;">✅ ' + data.message + '</span><br><small>Redirecting in 10 seconds...</small>';
                setTimeout(() => {
                    window.location.href = '{$systemurl}/clientarea.php?action=services';
                }, 10000);
            } else {
                msgBox.innerHTML = '<span style="color:red;">❌ ' + data.message + '</span>';
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.textContent = 'Submit UTR';
            msgBox.style.display = 'block';
            msgBox.innerHTML = '<span style="color:red;">⚠️ Something went wrong. Please try again.</span>';
        });
    });
});
</script>
