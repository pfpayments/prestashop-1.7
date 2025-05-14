<?php
if (! defined('_PS_VERSION_')){
	exit();
}
/**
 * Represents a mail attachment.
 *
 * @author Thomas Hunziker
 *
 */
class MailMessageAttachment {

	private $content;

	private $name;

	private $mimeType;

	/**
	 * Constructor.
	 *
	 * Either the input as defined by Mail::send() for mail attachment
	 * or a mail attachment. In later case the attachment is copied.
	 *
	 * @param array|MailMessageAttachment $input
	 */
	public function __construct($input = null) {
		if ($input instanceof MailMessageAttachment) {
			$this->content = $input->content;
			$this->name = $input->name;
			$this->mimeType = $input->mimeType;
		}
		else if (is_array($input)) {
			foreach ($input as $sub) {
				if (is_array($sub) && isset($sub['content'])) {
					$input = $sub;
					break;
				}
			}

			if (isset($input['content'])) {
				$this->setContent($input['content']);
			}
			if (isset($input['name'])) {
				$this->setName($input['name']);
			}
			if (isset($input['mime'])) {
				$this->setMimeType($input['mime']);
			}
		}
	}

	/**
	 * Returns the attachment content.
	 *
	 * @return string
	 */
	public function getContent(){
		return $this->content;
	}

	/**
	 * Sets the attachment content. This may be binary data.
	 *
	 * @param string $content
	 * @return MailMessageAttachment
	 */
	public function setContent($content){
		$this->content = $content;
		return $this;
	}

	/**
	 * Returns the name shown to the customer in the mail message.
	 *
	 * @return string
	 */
	public function getName(){
		return $this->name;
	}

	/**
	 * Sets the name shown to the customer in the mail message.
	 *
	 * @param string $name
	 * @return MailMessageAttachment
	 */
	public function setName($name){
		$this->name = $name;
		return $this;
	}

	/**
	 * Returns the mime type of the attachment. E.g. application/pdf, text/html.
	 *
	 * @return string
	 */
	public function getMimeType(){
		return $this->mimeType;
	}

	/**
	 * Sets the mime type of the mail attachment. E.g. application/pdf, text/html.
	 *
	 * @param string $mimeType
	 * @return MailMessageAttachment
	 */
	public function setMimeType($mimeType){
		$this->mimeType = $mimeType;
		return $this;
	}

	/**
	 * Returns the attachment as an array. This method is
	 * need to provide the correct input for Mail::send().
	 *
	 * @return array
	 */
	public function toArray() {
		return array(
			'content' => $this->getContent(),
			'name' => $this->getName(),
			'mime' => $this->getMimeType(),
		);
	}



}
