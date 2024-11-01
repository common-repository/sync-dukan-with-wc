<style>
    #process img {
        max-width: 50px;
    }
    #process { display: none }
</style>
<div id="dashboard-widgets-wrap">
    <form id="dashboard-widgets" class="metabox-holder">
        <div class="postbox-container">
            <form>
                <label for="shopify_token">
                    App Token:
                </label>
                <input type="text" id="shopify_token" name="shopify_token" placeholder="37b93413386cxxxxxxxxxxxxx06b6fcf415ade11" />
                <br>
                <label for="app_key">
                    App Key:
                </label>
                <input type="text" id="app_key" name="app_key" placeholder="329a01fddb5axxxxxxxx0b02c579c85f" />
                <br>
                <label for="app_id">
                    App Id:
                </label>
                <input type="number" min="1" id="app_id" name="app_id" placeholder="7" />
            </form>
        </div>
        <div class="postbox-container">
            <label for="dukan_token">
                Dukan Token:
            </label>
            <input type="text" id="dukan_token" name="dukan_token" placeholder="044895f4069dxxxxxxxef4ad1fffe4f6" />
            <br>
            <label for="store_url">
                Store URL (Currently accepting anything):
            </label>
            <input type="url" id="store_url" name="store_url" placeholder="https://myshop.dukan.pk/catalog" />
            <br>
            <label for="store_token">
                Store Token (Currently accepting anything):
            </label>
            <input type="text" id="store_token" name="store_token" placeholder="45761316125" />
            <br>
            <label for="app_id">
                Email (Currently accepting anything):
            </label>
            <input type="email" id="email" name="email" placeholder="waqar@example.com" />
            <input type="hidden" value="sync_dukan_action" name="action" />
        </div>
        <div class="postbox-container">
            <div id="response"></div>
            <div id="process">
                <img src="<?php echo plugin_dir_url( dirname( __FILE__ ) ) . 'img/processing.gif'; ?>" alt="Source GIF" />
            </div>
            <input type="submit" id="syncDukan" value="submit" />
        </div>
    </form>
</div>
<style>
    label, input {
        display: block;
        width: 100%;
    }
    .postbox-container {
        float: left;
        width: 48%;
        margin: 5px;
    }
</style>
