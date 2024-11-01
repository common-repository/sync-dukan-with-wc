<div id="dashboard-widgets-wrap">
    <div id="dashboard-widgets" class="metabox-holder">
        <div class="postbox-container">
            <h1>Sync Dukan</h1>

            <blockquote class="error-message">
                Connection Code: <?= $d_access_code; ?>
            </blockquote>

            <?php if($dukan_connection): ?>
            <button id="disconnectDukan" class="button button-primary">DISCONNECT DUKAN.pk</button>
            <?php endif; ?>
            
        </div>
    </div>
</div>
