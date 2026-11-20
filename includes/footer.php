<?php
/**
 * includes/footer.php
 * Closes the page, adds the shared delete-confirmation dialog and the scripts.
 */
?>
</main>

<footer class="app-footer">
    <div class="container">Library Management System &ndash; Database II, CAT 2</div>
</footer>

<!-- One confirmation dialog shared by every delete button -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="confirmTitle">Please confirm</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmYes">Yes, delete</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= e(base_url('assets/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
