import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["bar", "percentage", "form", "container"];

    upload(event) {
        event.preventDefault();

        const activeForm = event.currentTarget;
        const formActionUrl = activeForm.action;
        const formData = new FormData(activeForm);
        const xhr = new XMLHttpRequest();

        this.containerTarget.classList.remove('d-none');

        xhr.upload.addEventListener("progress", (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                this.barTarget.style.width = `${percent}%`;
                this.percentageTarget.innerText = percent;
            }
        });

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        window.scrollTo(0, 0);
                        return;
                    }
                } catch (e) {
                    console.warn("Expected JSON redirect payload, reloading instead");
                }

                window.location.reload();
                return;
            }

            if (xhr.status === 422) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(xhr.responseText, 'text/html');

                let freshFormMarkup = null;
                if (activeForm.name) {
                    freshFormMarkup = doc.querySelector(`form[name="${activeForm.name}"]`);
                }

                if (!freshFormMarkup) {
                    const escapedUrl = formActionUrl.replace(/"/g, '\\"');
                    freshFormMarkup = doc.querySelector(`form[action="${escapedUrl}"]`);
                }

                if (freshFormMarkup) {
                    activeForm.innerHTML = freshFormMarkup.innerHTML;
                    this.barTarget.style.width = `0%`;
                    this.percentageTarget.innerText = 0;
                } else {
                    const message = "Unknown file upload error";
                    const errorResult = Swal.fire({
                        title: "Upload failed",
                        text: message,
                        icon: "error",
                        confirmButtonColor: "gray",
                    });
                }
                this.containerTarget.classList.add('d-none');
            }
        };

        xhr.open("POST", formActionUrl);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    }
}
