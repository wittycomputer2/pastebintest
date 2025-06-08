document.addEventListener('DOMContentLoaded', function() {
    const pasteForm = document.getElementById('pasteForm');

    if (pasteForm) {
        pasteForm.addEventListener('submit', function(event) {
            const confirmation = confirm("Are you sure you want to create this paste?");
            if (!confirmation) {
                event.preventDefault();
            }
        });
    }
});
