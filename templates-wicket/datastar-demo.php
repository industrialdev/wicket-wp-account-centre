<?php
/**
 * Datastar Integration Demo Page.
 *
 * This template demonstrates how to integrate Datastar with the Wicket Account Centre.
 * Include this in a WordPress page or template.
 */
?>

<div class="wicket-acc-datastar-demo max-w-4xl mx-auto p-6">
  <h1 class="text-3xl font-bold mb-8">Datastar Integration Demo</h1>

  <!-- Profile Update Demo -->
  <section class="mb-12">
    <h2 class="text-2xl font-semibold mb-4">Profile Update with Datastar</h2>
    <p class="text-gray-600 mb-4">This demonstrates updating user profile information using Datastar forms.</p>

    <div class="border border-gray-200 rounded-lg p-6">
      <?php
            // Include the profile update form
            echo '<div data-load="' . WicketAcc\HypermediaApi::get_endpoint_url('profile-update') . '"></div>';
?>
    </div>
  </section>

  <!-- Server-Sent Events Demo -->
  <section class="mb-12">
    <h2 class="text-2xl font-semibold mb-4">Server-Sent Events Demo</h2>
    <p class="text-gray-600 mb-4">This demonstrates real-time communication using Datastar's SSE capabilities.</p>

    <div class="border border-gray-200 rounded-lg p-6" data-signals-delay="100"
      data-signals-message="Hello from Wicket Account Centre!">
      <div class="mb-4">
        <label for="delay" class="block text-sm font-medium text-gray-700 mb-2">
          Delay (milliseconds)
        </label>
        <input data-bind-delay id="delay" type="number" step="100" min="0" value="100"
          class="block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </div>

      <div class="mb-4">
        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
          Message
        </label>
        <input data-bind-message id="message" type="text" value="Hello from Wicket Account Centre!"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
      </div>

      <button
        data-on-click="@get('<?php echo WicketAcc\HypermediaApi::get_endpoint_url('datastar-demo'); ?>')"
        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        Start SSE Stream
      </button>

      <div id="datastar-output" class="mt-4 p-4 bg-gray-50 rounded min-h-[2rem] font-mono"></div>
    </div>
  </section>

  <!-- Preference Saving Demo -->
  <section class="mb-12">
    <h2 class="text-2xl font-semibold mb-4">User Preferences (No Swap)</h2>
    <p class="text-gray-600 mb-4">This demonstrates saving user preferences without page refresh using no-swap
      endpoints.</p>

    <div class="border border-gray-200 rounded-lg p-6">
      <div class="space-y-4">
        <div>
          <label class="flex items-center">
            <input type="checkbox"
              data-on-change="@post('<?php echo WicketAcc\HypermediaApi::get_endpoint_url('noswap/save-preference'); ?>', {preference_key: 'notifications_enabled', preference_value: this.checked ? '1' : '0'})"
              class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <span class="ml-2 text-sm text-gray-700">Enable notifications</span>
          </label>
        </div>

        <div>
          <label class="flex items-center">
            <input type="checkbox"
              data-on-change="@post('<?php echo WicketAcc\HypermediaApi::get_endpoint_url('noswap/save-preference'); ?>', {preference_key: 'dark_mode', preference_value: this.checked ? '1' : '0'})"
              class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <span class="ml-2 text-sm text-gray-700">Dark mode</span>
          </label>
        </div>
      </div>

      <div id="preference-status" class="mt-4 text-sm text-gray-600"></div>
    </div>
  </section>
</div>

<script>
  // Listen for custom events triggered by the no-swap endpoints
  document.addEventListener('preference-saved', function() {
    const statusEl = document.getElementById('preference-status');
    statusEl.textContent = 'Preference saved successfully!';
    statusEl.className = 'mt-4 text-sm text-green-600';
    setTimeout(() => {
      statusEl.textContent = '';
    }, 3000);
  });

  document.addEventListener('preference-error', function() {
    const statusEl = document.getElementById('preference-status');
    statusEl.textContent = 'Error saving preference. Please try again.';
    statusEl.className = 'mt-4 text-sm text-red-600';
    setTimeout(() => {
      statusEl.textContent = '';
    }, 3000);
  });
</script>

<style>
  .cursor {
    animation: blink 1s infinite;
  }

  @keyframes blink {

    0%,
    50% {
      opacity: 1;
    }

    51%,
    100% {
      opacity: 0;
    }
  }
</style>
