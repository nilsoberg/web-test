document.addEventListener("DOMContentLoaded", function () {
    initializeCGFPOptionASubmission("CGFPoptionA", "submit-cgfp-opt-a");
});

function initializeCGFPOptionASubmission(prefix, endpoint) {
    const container = document.getElementById(prefix);
    if (!container) return;

    const submitBtn = container.querySelector(".submit-job");
    if (!submitBtn) return;

    submitBtn.addEventListener("click", function () {
        const email = document.getElementById(`${prefix}-email`)?.value?.trim();
        const fileInput = document.getElementById(`${prefix}-fasta-file`);
        const file = fileInput?.files?.[0];

        const minSeqLen = document.getElementById("min-seq-length")?.value?.trim();
        const maxSeqLen = document.getElementById("max-seq-length")?.value?.trim();
        const refDb = document.getElementById(`${prefix}-section-2-refdb`)?.value;
        const cdhit = document.getElementById(`${prefix}-section-2-cdhit-seq-id`)?.value?.trim();
        const searchType = document.getElementById(`${prefix}-section-2-ssn-search-type`)?.value;

        const errors = [];

        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|org|gov|io|edu|net)$/;
        if (!email || !emailRegex.test(email)) {
            errors.push("A valid email address is required.");
        }

        if (!file) {
            errors.push("You must upload a Colored SSN file.");
        } else {
            const filename = file.name.toLowerCase();
            if (!filename.endsWith(".xgmml") && !filename.endsWith(".xgmml.zip")) {
                errors.push("Only .xgmml and .xgmml.zip files are accepted.");
            }
        }

        if (minSeqLen && isNaN(parseInt(minSeqLen))) {
            errors.push("Minimum sequence length must be numeric.");
        }
        if (maxSeqLen && isNaN(parseInt(maxSeqLen))) {
            errors.push("Maximum sequence length must be numeric.");
        }
        if (cdhit && isNaN(parseFloat(cdhit))) {
            errors.push("CD-HIT sequence identity must be numeric.");
        }

        if (errors.length > 0) {
            alert(errors.join("\n"));
            return;
        }

        const formData = new FormData();
        formData.append("identify_email", email);
        formData.append("identify_file", file);
        formData.append("identify_min_seq_len", minSeqLen || "");
        formData.append("identify_max_seq_len", maxSeqLen || "");
        formData.append("identify_ref_db", refDb || "");
        formData.append("identify_cdhit_sid", cdhit || "");
        formData.append("identify_search_type", searchType || "");

        fetch(`/submit-job/${endpoint}`, {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.identify_id) {
                window.location.href = "/submit-job/job-submission-success";
            } else {
                window.location.href = "/submit-job/job-submission-error";
            }
        })
        .catch(() => {
            window.location.href = "/submit-job/job-submission-error";
        });        
    });
}