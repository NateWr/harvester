<?php

/**
 * @file ProfileHandler.inc.php
 *
 * Copyright (c) 2005-2012 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProfileHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for modifying user profiles.
 */


import('pages.user.UserHandler');

class ProfileHandler extends UserHandler {

	/**
	 * Display form to edit user's profile.
	 */
	function profile($args, &$request) {
		$this->validate();
		$this->setupTemplate($request, true);

		import('classes.user.form.ProfileForm');

		$profileForm = new ProfileForm($request->getUser(), $request->getSite());
		if ($profileForm->isLocaleResubmit()) {
			$profileForm->readInputData();
		} else {
			$profileForm->initData();
		}
		$profileForm->display();
	}

	/**
	 * Validate and save changes to user's profile.
	 */
	function saveProfile($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);
		$dataModified = false;

		import('classes.user.form.ProfileForm');

		$profileForm = new ProfileForm();
		$profileForm->readInputData();

		if ($request->getUserVar('uploadProfileImage')) {
			if (!$profileForm->uploadProfileImage()) {
				$profileForm->addError('profileImage', __('user.profile.form.profileImageInvalid'));
			}
			$dataModified = true;
		} else if ($request->getUserVar('deleteProfileImage')) {
			$profileForm->deleteProfileImage();
			$dataModified = true;
		}

		if (!$dataModified && $profileForm->validate()) {
			$profileForm->execute();
			$request->redirect($request->getRequestedPage());
		} else {
			$this->setupTemplate($request, true);
			$profileForm->display();
		}
	}

	/**
	 * Display form to change user's password.
	 */
	function changePassword($args, &$request) {
		$this->validate();
		$this->setupTemplate($request, true);

		import('classes.user.form.ChangePasswordForm');

		$passwordForm = new ChangePasswordForm();
		$passwordForm->initData();
		$passwordForm->display();
	}

	/**
	 * Save user's new password.
	 */
	function savePassword($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);

		import('classes.user.form.ChangePasswordForm');

		$passwordForm = new ChangePasswordForm();
		$passwordForm->readInputData();

		$this->setupTemplate($request, true);
		if ($passwordForm->validate()) {
			$passwordForm->execute();
			$request->redirect($request->getRequestedPage());

		} else {
			$passwordForm->display();
		}
	}
}

?>
