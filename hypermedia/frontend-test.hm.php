<?php
/**
 * Datastar Frontend Test Template.
 *
 * This creates a simple test page to verify Datastar is loaded and functional.
 * Access this template via: /wp-html/v1/wicket-acc:frontend-test
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Datastar Frontend Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-box { background: #f0f0f0; padding: 20px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; }
        button { padding: 10px 20px; margin: 5px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer; }
        input { padding: 8px; margin: 5px; border: 1px solid #ccc; border-radius: 3px; }
    </style>
    <?php wp_head(); ?>
</head>
<body>
    <div class="test-box">
        <h1>Datastar Frontend Test</h1>
        <p>This page tests if Datastar is properly loaded and configured.</p>
    </div>

    <div class="test-box">
        <h2>1. Script Detection Test</h2>
        <div id="script-check">Checking for Datastar...</div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const checkEl = document.getElementById('script-check');
                if (typeof window.ds !== 'undefined' || typeof window.Datastar !== 'undefined' || document.querySelector('script[src*="datastar"]')) {
                    checkEl.innerHTML = '<span style="color: green;">✓ Datastar appears to be loaded!</span>';
                    checkEl.className = 'success';
                } else {
                    checkEl.innerHTML = '<span style="color: red;">✗ Datastar not detected</span>';
                    checkEl.className = 'error';
                }
            });
        </script>
    </div>

    <div class="test-box">
        <h2>2. Basic Datastar Test</h2>
        <div data-signals-counter="0">
            <p>Counter: <span data-text="$counter"></span></p>
            <button data-on-click="$counter++">Increment</button>
            <button data-on-click="$counter--">Decrement</button>
            <button data-on-click="$counter = 0">Reset</button>
        </div>
    </div>

    <div class="test-box">
        <h2>3. AJAX Test</h2>
        <button data-on-click="@get('<?php echo WicketAcc\HypermediaApi::get_endpoint_url('debug-config'); ?>')"
                data-target="#ajax-result">
            Load Debug Info
        </button>
        <div id="ajax-result" style="margin-top: 10px;"></div>
    </div>

    <div class="test-box">
        <h2>4. Form Test</h2>
        <form data-on-submit="@post('<?php echo WicketAcc\HypermediaApi::get_endpoint_url('profile-update'); ?>')"
              data-target="#form-result">
            <input type="text" name="test_field" placeholder="Test input" required>
            <button type="submit">Submit Test</button>
        </form>
        <div id="form-result" style="margin-top: 10px;"></div>
    </div>

    <div class="test-box">
        <h2>5. Endpoint URLs Test</h2>
        <ul>
            <li>Debug Config: <code><?php echo WicketAcc\HypermediaApi::get_endpoint_url('debug-config'); ?></code></li>
            <li>Profile Update: <code><?php echo WicketAcc\HypermediaApi::get_endpoint_url('profile-update'); ?></code></li>
            <li>Datastar Demo: <code><?php echo WicketAcc\HypermediaApi::get_endpoint_url('datastar-demo'); ?></code></li>
        </ul>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
