<style>
    #process img {
        max-width: 50px;
    }
    #process { display: none }
</style>

<div id="dashboard-widgets-wrap">
    <div id="dashboard-widgets" class="metabox-holder">
        <div class="postbox-container">
            <h1>Orders</h1>
            <div id="response"></div>
            <div id="process">
                <img src="<?php echo plugin_dir_url( dirname( __FILE__ ) ) . 'img/processing.gif'; ?>" alt="Source GIF" />
            </div>
            <button id="getOrdersFromDukanStore"
                    class="button button-primary">Get Orders</button>
        </div>
    </div>
</div>
