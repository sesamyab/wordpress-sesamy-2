import '../css/admin.css';

(function ($) {
	if (!window.inlineEditPost) {
		return;
	}

	const wp_inline_edit = window.inlineEditPost.edit;
	window.inlineEditPost.edit = function (id, ...args) {
		wp_inline_edit.apply(this, [id, ...args]);

		const row = $(id).closest('tr');
		const post_id = row.attr('id').replace(/^post-/, '');

		if (post_id) {
			const locked = row.find('.column-sesamy_locked').length > 0;
			const single_purchase = row.find('.column-sesamy_single_purchase').length > 0;
			const inline_edit = $(`#edit-${post_id}`);
			inline_edit.find('input[name="_sesamy_locked"]').prop('checked', locked);
			inline_edit
				.find('input[name="_sesamy_enable_single_purchase"]')
				.prop('checked', single_purchase);
		}
	};
})(window.jQuery);
