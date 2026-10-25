<?php if (is_logged_in()): ?>
    </main>
</div><!-- /.app-wrapper -->
<?php else: ?>
    </div><!-- /.container -->
<?php endif; ?>

<footer class="footer mt-auto py-3 bg-white border-top no-print">
    <div class="container-fluid text-center">
        <span class="text-muted small">&copy; <?php echo date('Y'); ?> Libera - Enterprise Library Management & Double-Entry Accounting System. All rights reserved.</span>
    </div>
</footer>
<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
