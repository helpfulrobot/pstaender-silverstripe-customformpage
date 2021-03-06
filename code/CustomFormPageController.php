<?php

class CustomFormPage_Controller extends Page_Controller {

	private static $allowed_actions = [
		'Form' => true,
	];

	function Form() {
		$formFields = $this->dataRecord->FormFields();
		if ($formFields) {
			$fields = $formFields['fields'];
			$required = $formFields['required'];
			$submit = FormAction::create('doSubmitForm', _t('CustomFormPage.Submit', 'Submit'));

			$actions = FieldList::create();
			if ($this->dataRecord->DisplayResetButton) {
				$actions->push(ResetFormAction::create('Reset', _t('CustomFormPage.Reset', 'Reset')));
			}
			$actions->push($submit);
			$form = Form::create(
				$this,
				'Form',
				FieldList::create($fields),
				$actions
			);
			if (sizeof($required) > 0) {
				$form->setValidator($required);
			}
			return $form;
		}
	}


	function doSubmitForm($data, Form $form) {
		$this->addFormSubmission($data, $form);
		if ($this->dataRecord->PageOnSuccessID > 0) {
			return $this->redirect($this->dataRecord->PageOnSuccess()->Link());
		} else {
			return $this->redirectBack();
		}
	}

	private function addFormSubmission($data, Form $form) {
		$submission = CustomFormPageSubmission::create();
		$submission->PageID = $this->dataRecord->ID;
		$submission->SerializeData($data);
		$submission->write();

		if ($this->SendToEmailInternal) {
			$email = new Email();
			$from = ($this->FromEmail) ? $this->FromEmail : $this->SendToEmailInternal;
			$to = $this->SendToEmailInternal;
			$subject = ($this->EmailSubjectInternal) ? $this->EmailSubjectInternal : 'New Form Submission';
			$email
				->setFrom($from)
				->setTo($to)
				->setSubject($subject)
				->setTemplate('InternalSubmissionEmail')
				->populateTemplate(new ArrayData([
					'Submission' => $submission,
				]));

			$submission->SendToEmail = $to;
			$submission->IsSended = $email->send();
			$submission->write();
		}

		if ($submission->IsSended && $this->dataRecord->MessageIfSubmissionSuccessful) {
			$form->sessionMessage($this->dataRecord->MessageIfSubmissionSuccessful, 'good');
		} else if ($this->SendToEmailInternal && !$submission->IsSended) {
			$form->sessionMessage($this->MessageIfSubmissionFailed ? $this->MessageIfSubmissionFailed : _t('CustomFormPage.MessageOnSendingFailed', 'There was a problem submitting your data. Please get in touch with us via email.'), 'bad');
		}
		return $submission;
	}

}
