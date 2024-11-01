<style>
    #process img {
        max-width: 50px;
    }
    #process { display: none }
</style>
<div id="dashboard-widgets-wrap">
    <div id="dashboard-widgets" class="metabox-holder">
        <div class="postbox-container">
            <h1>Products</h1>
            <div id="response"></div>
            <div id="process">
                <img src="<?php echo plugin_dir_url( dirname( __FILE__ ) ) . 'img/processing.gif'; ?>"
                     alt="Source GIF" />
            </div>
            <button id="getProductsFromDukanStore" class="button button-primary">Get Products</button>
        </div>
    </div>
</div>
