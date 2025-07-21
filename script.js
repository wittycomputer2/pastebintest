document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('workbook-form');
    const inputs = form.querySelectorAll('textarea');
    const progressBar = document.querySelector('.progress-bar');
    const progressPercentage = document.getElementById('progress-percentage');

    const totalInputs = inputs.length;

    function autoSave() {
        inputs.forEach(input => {
            const key = 'essence_guide_' + input.id;
            // Load saved data
            const savedValue = localStorage.getItem(key);
            if (savedValue) {
                input.value = savedValue;
            }

            // Save data on input
            input.addEventListener('input', () => {
                localStorage.setItem(key, input.value);
                updateProgress();
            });
        });
    }

    function updateProgress() {
        let completedInputs = 0;
        inputs.forEach(input => {
            if (input.value.trim() !== '') {
                completedInputs++;
            }
        });

        const percentage = totalInputs > 0 ? (completedInputs / totalInputs) * 100 : 0;
        progressBar.style.width = percentage + '%';
        progressPercentage.textContent = Math.round(percentage);
    }

    autoSave();
    updateProgress();

    const downloadButton = document.getElementById('download-pdf');
    downloadButton.addEventListener('click', () => {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        let y = 15; // Initial y position

        doc.setFont('Playfair Display', 'bold');
        doc.setFontSize(22);
        doc.text('Essence Words Discovery Guide', 105, y, { align: 'center' });
        y += 10;

        doc.setFont('Source Sans Pro', 'normal');
        doc.setFontSize(12);

        const prompts = document.querySelectorAll('.prompt');
        prompts.forEach((prompt, index) => {
            if (y > 280) { // Add new page if content exceeds page height
                doc.addPage();
                y = 15;
            }

            const title = prompt.querySelector('h3').textContent;
            doc.setFont('Playfair Display', 'bold');
            doc.setFontSize(16);
            doc.text(title, 15, y);
            y += 8;

            doc.setFont('Source Sans Pro', 'normal');
            doc.setFontSize(12);

            const questions = prompt.querySelectorAll('.question');
            questions.forEach(question => {
                const label = question.querySelector('label').textContent;
                const textarea = question.querySelector('textarea');
                const value = textarea.value;

                if (y > 280) {
                    doc.addPage();
                    y = 15;
                }

                doc.setFont(undefined, 'bold');
                doc.text(label, 15, y);
                y += 6;
                doc.setFont(undefined, 'normal');
                const splitText = doc.splitTextToSize(value, 180);
                doc.text(splitText, 15, y);
                y += (splitText.length * 5) + 5;
            });

            const aiInstructions = prompt.querySelector('.ai-instructions');
            if (aiInstructions) {
                const aiResult = aiInstructions.querySelector('textarea').value;
                if (aiResult) {
                    if (y > 280) {
                        doc.addPage();
                        y = 15;
                    }
                    doc.setFont(undefined, 'bold');
                    doc.text("AI Analysis Results:", 15, y);
                    y += 6;
                    doc.setFont(undefined, 'normal');
                    const splitText = doc.splitTextToSize(aiResult, 180);
                    doc.text(splitText, 15, y);
                    y += (splitText.length * 5) + 5;
                }
            }

            const journalingExercise = prompt.querySelector('.journaling-exercise');
            if (journalingExercise) {
                const journalEntry = journalingExercise.querySelector('textarea').value;
                if (journalEntry) {
                    if (y > 280) {
                        doc.addPage();
                        y = 15;
                    }
                    doc.setFont(undefined, 'bold');
                    doc.text("Journaling Exercise:", 15, y);
                    y += 6;
                    doc.setFont(undefined, 'normal');
                    const splitText = doc.splitTextToSize(journalEntry, 180);
                    doc.text(splitText, 15, y);
                    y += (splitText.length * 5) + 5;
                }
            }

            y += 5; // Add some space between prompts
        });

        doc.save('Essence_Words_Discovery_Guide.pdf');
    });
});
