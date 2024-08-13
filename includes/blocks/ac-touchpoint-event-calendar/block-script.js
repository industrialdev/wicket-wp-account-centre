// Vanilla DOM ready
document.addEventListener('DOMContentLoaded', function () {
	// Set up click event listeners for close elements inside the modals
	document.querySelectorAll('.close-modal').forEach(card => {
		card.addEventListener('click', wicketAccCloseModal);
	});

	// Remove duplicated modals
	wicketAccRemoveDuplicatedModals();
});

// Function to open the modal within a specific event card
if (typeof wicketAccOpenModal !== 'undefined') {
	function wicketAccOpenModal(event) {
		event.preventDefault();
		const eventCard = event.currentTarget;
		const modal = eventCard.querySelector('.wicketAcc-modal');

		// To prevent multiple modals from opening at the same time
		document.querySelectorAll('.event-card').forEach(card => {
			card.removeEventListener('click', wicketAccOpenModal);
		});

		if (modal) {
			console.log(modal);
			modal.classList.remove('hidden');
		}
	}
}

// Function to close the modal within the specific event card
if (typeof wicketAccCloseModal !== 'undefined') {
	function wicketAccCloseModal(event) {
		event.preventDefault();
		const button = event.target;
		const modal = button.closest('.wicketAcc-modal');

		if (modal) {
			console.log(modal);
			modal.classList.add('hidden');

			// Reattach the click event listener to the event cards
			setTimeout(() => {
				document.querySelectorAll('.event-card').forEach(card => {
					card.addEventListener('click', wicketAccOpenModal);
				});
			}, 250);
		}
	}
}

// Detect and remove duplicated modals .wicketAcc-modal
if (typeof wicketAccRemoveDuplicatedModals !== 'undefined') {
	function wicketAccRemoveDuplicatedModals() {
		const modals = document.querySelectorAll('.wicketAcc-modal');

		// Check if there is more than one modal
		if (modals.length > 1) {
			// Iterate over the NodeList starting from the second element
			for (let i = 1; i < modals.length; i++) {
				// Remove each additional modal from the DOM
				modals[i].parentNode.removeChild(modals[i]);
			}
		}
	}
}
