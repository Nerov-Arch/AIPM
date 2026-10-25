        </main>
        <footer class="app-footer"><span>© <?= date('Y') ?> AIP Management System · MPDO Indang, Cavite</span><span>Development build</span></footer>
    </div>
</div>
<dialog class="detail-dialog" data-detail-dialog aria-labelledby="detail-dialog-title">
    <div class="dialog-head">
        <div><p class="eyebrow" data-detail-eyebrow></p><h2 id="detail-dialog-title" data-detail-title></h2></div>
        <button class="dialog-close" type="button" aria-label="Close" data-dialog-close>×</button>
    </div>
    <dl class="detail-list" data-detail-body></dl>
    <p class="dialog-note" data-detail-note></p>
    <div class="dialog-actions"><button class="secondary-button" type="button" data-dialog-close>Close</button></div>
</dialog>
<script src="<?= $assetPrefix ?>assets/js/script.js"></script>
</body>
</html>
