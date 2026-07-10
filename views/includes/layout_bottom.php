</div><!-- /.page -->
<style>
    :root {
        --text: #fff !important;
        --muted: #fff !important;
        --text-muted: #fff !important;
        --text-label: #fff !important;
    }

    html,
    body {
        font-size: 1.2rem !important;
        color: #fff !important;
    }

    body,
    button,
    input,
    select,
    textarea,
    .page,
    .page *,
    body * {
        font-family: 'Cairo', sans-serif !important;
        color: #fff !important;
        -webkit-text-fill-color: #fff !important;
    }

    .page {
        font-size: 1.2rem !important;
    }

    table th,
    table td {
        text-align: center !important;
    }
</style>
<script>
    // Mobile nav toggle
    const toggle = document.getElementById('navToggle');
    const links  = document.querySelector('.nav-links');
    toggle.addEventListener('click', () => {
        const open = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', e => {
        if (!toggle.contains(e.target) && !links.contains(e.target))
            links.classList.remove('open');
    });

    // Active nav link
    (function() {
        const path = location.pathname;
        document.querySelectorAll('.nav-link').forEach(a => {
            if (a.getAttribute('href') && path.startsWith(a.getAttribute('href'))) {
                a.classList.add('active');
            }
        });
    })();

    // Send email form (only exists on pages that include it)
    document.getElementById('send-email-form')
        ?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = this;
        const button = document.getElementById('send-email-btn');
        const buttonText = document.getElementById('email-btn-text');
        const messageBox = document.getElementById('email-message');
        // loading state
        button.disabled = true;
        buttonText.textContent = 'جاري الإرسال...';
        messageBox.innerHTML = '';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            });
            const data = await response.json();
            if (data.success) {
                messageBox.innerHTML = `
                    <div class="success-banner">
                        ✅ تم إرسال البريد الإلكتروني بنجاح.
                    </div>
                `;
            } else {
                messageBox.innerHTML = `
                    <div class="error-banner">
                        ⚠️ ${data.message}
                    </div>
                `;
            }
        } catch (error) {
            messageBox.innerHTML = `
                <div class="error-banner">
                    ⚠️ حدث خطأ أثناء إرسال البريد الإلكتروني.
                </div>
            `;
            console.error(error);
        } finally {
            button.disabled = false;
            buttonText.textContent = 'إرسال بريد إلكتروني / Send Email';
        }
    });
</script>
</body>
</html>
