document.addEventListener('DOMContentLoaded', function() {
    const pasteForm = document.getElementById('pasteForm');
    const resultMessageDiv = document.getElementById('resultMessage');

    if (!pasteForm) {
        console.error("Error: Paste form not found.");
        return;
    }
    if (!resultMessageDiv) {
        console.error("Error: Result message div not found.");
        // You might want to create it dynamically if it's critical and might be missing
        // For now, we'll assume it should be in the HTML.
    }

    pasteForm.addEventListener('submit', function(event) {
        event.preventDefault(); // Stop default synchronous submission

        const formData = new FormData(pasteForm);

        // Clear previous results
        if(resultMessageDiv) resultMessageDiv.innerHTML = '';

        fetch('create.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                // Try to parse JSON error body if server returns it, otherwise generic error
                return response.json().catch(() => {
                    throw new Error(`Server error: ${response.status} ${response.statusText}`);
                }).then(errorData => {
                    throw { serverError: true, data: errorData }; // Custom error structure
                });
            }
            return response.json();
        })
        .then(data => {
            displayPasteResult(data, pasteForm);
        })
        .catch(error => {
            console.error('Error:', error);
            let errorMessage;
            if (error.serverError && error.data && error.data.message) {
                errorMessage = error.data.message;
            } else if (error.message) {
                errorMessage = error.message;
            } else {
                errorMessage = 'An error occurred while creating the paste. Please try again.';
            }
            displayPasteResult({ status: 'error', message: errorMessage });
        });
    });

    function displayPasteResult(data, form = null) {
        if (!resultMessageDiv) return; // Should not happen if initial check passed

        resultMessageDiv.innerHTML = ''; // Clear previous messages

        const messageP = document.createElement('p');
        messageP.textContent = data.message;

        if (data.status === 'success') {
            messageP.className = 'success-message'; // For styling if needed
            resultMessageDiv.appendChild(messageP);

            const urlLabel = document.createElement('label');
            urlLabel.htmlFor = 'pasteUrl';
            urlLabel.textContent = 'Your Paste URL:';
            urlLabel.style.display = 'block';
            urlLabel.style.marginTop = '10px';
            resultMessageDiv.appendChild(urlLabel);

            const urlInput = document.createElement('input');
            urlInput.type = 'text';
            urlInput.id = 'pasteUrl';
            urlInput.value = data.url;
            urlInput.readOnly = true;
            urlInput.style.width = '100%';
            urlInput.style.marginTop = '5px';
            urlInput.style.marginBottom = '10px'; // Added margin bottom
            urlInput.style.padding = '8px'; // Consistent padding
            urlInput.style.backgroundColor = '#555'; // Darker for readonly
            urlInput.style.color = '#fff';
            urlInput.style.border = '1px solid #666';


            resultMessageDiv.appendChild(urlInput);

            // Auto-select the URL
            urlInput.addEventListener('focus', function() {
                this.select();
            });
            urlInput.select(); // Select on creation

            // Add a copy button
            const copyButton = document.createElement('button');
            copyButton.textContent = 'Copy URL';
            copyButton.type = 'button'; // Prevent form submission
            copyButton.style.padding = '8px 12px';
            copyButton.style.backgroundColor = '#e6e77d';
            copyButton.style.color = '#292929';
            copyButton.style.border = 'none';
            copyButton.style.borderRadius = '4px';
            copyButton.style.cursor = 'pointer';

            copyButton.addEventListener('click', function() {
                urlInput.select();
                try {
                    document.execCommand('copy'); // Deprecated but widely supported
                    // You could update button text to "Copied!" temporarily
                    copyButton.textContent = 'Copied!';
                    setTimeout(() => { copyButton.textContent = 'Copy URL'; }, 2000);
                } catch (err) {
                    console.warn('Fallback: Could not copy text automatically. User may need to manually copy.');
                    // alert("Could not copy automatically. Please press Ctrl+C.");
                }
            });
            resultMessageDiv.appendChild(copyButton);


            if (form) {
                form.reset(); // Clear the form fields
            }
        } else { // Error status
            messageP.className = 'error-message'; // For styling if needed
            messageP.style.color = '#ff6b6b'; // Example error color
            resultMessageDiv.appendChild(messageP);
        }
    }
});
