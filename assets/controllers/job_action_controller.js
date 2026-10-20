import { Controller } from "@hotwired/stimulus";
import { Tooltip } from "bootstrap";

export default class extends Controller {
    static values = { url: String };

    async archive(event) {
        event.preventDefault();

        const confirmResult = await Swal.fire({
            title: "Archive Job",
            text: "Are you sure you want to archive this job?",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "gray",
            confirmButtonText: "Archive"
        });
        if (!confirmResult.isConfirmed)
			return;

        const button = this.element;
        const row = button.closest('tr');

        const bootstrapObj = window.bootstrap || await import('bootstrap');
        const tooltip = bootstrapObj.Tooltip.getInstance(button);

        const performRemoval = async () => {
            try {
                const response = await fetch(this.urlValue, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
    
                if (response.ok) {
                    const row = this.element.closest('tr');
                    const tbody = row.parentElement;
                    row.remove();
                } else if (response.status === 403) {
                    alert("You do not have permission to archive this job.");
                } else {
                    alert("An unexpected error occurred.");
                }
            } catch (error) {
                console.error("Archive request failed", error);
            }
        };

        if (tooltip) {
            tooltip.dispose();
        }
        await performRemoval();
    }
}
