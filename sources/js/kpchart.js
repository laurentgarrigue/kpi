// Vanilla JavaScript - no jQuery dependency
document.addEventListener('DOMContentLoaded', function(){

    // Initialize Bootstrap popovers for images
    const imageElements = document.querySelectorAll('.img2');
    imageElements.forEach(function(img) {
        const popover = new bootstrap.Popover(img, {
            html: true,
            trigger: 'hover',
            placement: 'right',
            content: function () {
                const temp = img.getAttribute('src');
                return '<img class="img-rounded" style="float:right;width:100px;max-width:100px;" src="'+temp+'" />';
            }
        });
    });

    // Share button functionality
    const shareBtn = document.getElementById('share_btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function(){
            const toCopy = window.location.href;
            const existingAlert = document.getElementById('share_alert');
            if (existingAlert) {
                existingAlert.remove();
            }

            const navTitle = document.getElementById('navTitle');
            if (navTitle) {
                navTitle.insertAdjacentHTML('afterend',
                    '<div class="alert alert-info alert-dismissible" role="alert" id="share_alert">'
                    + ' <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                    + '<span>' + toCopy + '</span><input type="text" id="share_link" value="' + toCopy + '">'
                    + '</div>');

                const shareLink = document.getElementById('share_link');
                if (shareLink) {
                    shareLink.select();
                    document.execCommand('copy');
                    shareLink.remove();
                }
            }
        });
    }

    // Smooth scroll to navigation group
    const navGroup = document.getElementById('navGroup');
    if (navGroup && navGroup.previousElementSibling) {
        const targetOffset = navGroup.previousElementSibling.offsetTop;
        window.scrollTo({
            top: targetOffset,
            behavior: 'smooth'
        });
    }

    // Highlight team on hover - equipe rouge au survol
    const equipeLinks = document.querySelectorAll('a.equipe');
    equipeLinks.forEach(function(link) {
        link.addEventListener('mouseenter', function(){
            const team = this.textContent;
            const btnLinks = document.querySelectorAll('a.btn');
            btnLinks.forEach(function(btn){
                if (btn.textContent === team) {
                    btn.classList.add('btn-danger');
                }
            });
        });

        link.addEventListener('mouseleave', function(){
            const dangerBtns = document.querySelectorAll('a.btn-danger');
            dangerBtns.forEach(function(btn){
                btn.classList.remove('btn-danger');
            });
        });
    });

});

