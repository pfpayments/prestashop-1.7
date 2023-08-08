<?php 
if (! defined('_PS_VERSION_')){
	exit();
}

/**
 * This class represents a mail message.
 * 
 * @author Thomas Hunziker
 *
 */
class MailMessage {
	
	private $langId;
	
	private $templateName;
	
	private $subject;
	
	private $templateVariables;
	
	private $toEmailAddress;
	
	private $toName;
	
	private $fromEmailAddress;
	
	private $fromName;
	
	private $fileAttachment = null;

	private $modeSmtp;
	
	private $templateFolderPath;
	
	private $shopId;
	
	private $bcc;
	
	private $replyTo;
	
	private $replyToName;
	
	/**
	 * Constructor. Allows to copy the input (copy contructor).
	 * 
	 * @param MailMessage $input (Optional)Message to copy
	 */
	public function __construct($input = null) {
		if ($input instanceof MailMessage) {
			if ($input->fileAttachment !== null) {
				$this->fileAttachment = new MailMessageAttachment($input->fileAttachment);
			}
			$this->fromEmailAddress = $input->fromEmailAddress;
			$this->fromName = $input->fromName;
			$this->langId = $input->langId;
			$this->shopId = $input->shopId;
			$this->subject = $input->subject;
			$this->templateFolderPath = $input->templateFolderPath;
			$this->modeSmtp = $input->modeSmtp;
			$this->templateName = $input->templateName;
			$this->templateVariables = $input->templateVariables;
			$this->toEmailAddress = $input->toEmailAddress;
			$this->toName = $input->toName;
			$this->bcc = $input->bcc;
			$this->replyTo = $input->replyTo;
			$this->replyToName = $input->replyToName;
		}
	}

	/**
	 * Returns the id of the language of the mail message.
	 * 
	 * @return string
	 */
	public function getLangId(){
		return $this->langId;
	}

	/**
	 * Sets the language id for the message.
	 * 
	 * @param int $langId
	 * @return MailMessage
	 */
	public function setLangId($langId){
		$this->langId = $langId;
		return $this;
	}

	/**
	 * Returns the name of the template to use for this message.
	 * 
	 * @return string
	 */
	public function getTemplateName(){
		return $this->templateName;
	}

	/**
	 * Sets the template to be used for this message.
	 * 
	 * @param string $templateName
	 * @return MailMessage
	 */
	public function setTemplateName($templateName){
		$this->templateName = $templateName;
		return $this;
	}

	/**
	 * Returns the message subject.
	 * 
	 * @return string
	 */
	public function getSubject(){
		return $this->subject;
	}

	/**
	 * Sets the subject of the message. The subject will be modified
	 * by the sending method by prepending the following string:
	 * [Shop Name]
	 * 
	 * The prefix can not be removed.
	 * 
	 * @param string $subject
	 * @return MailMessage
	 */
	public function setSubject($subject){
		$this->subject = $subject;
		return $this;
	}

	/**
	 * Returns the variables for the template. The variables
	 * will be replaced in the template. The result is the 
	 * body of the message.
	 * 
	 * e.g. array (
	 *   '{delivery_company}',
	 *   '{carrier}',
	 * )
	 * 
	 * @return array
	 */
	public function getTemplateVariables(){
		return $this->templateVariables;
	}

	/**
	 * Sets the variables for the template. The variables are replaced
	 * in the body of the message.
	 * 
	 * e.g. array (
	 *   '{delivery_company}',
	 *   '{carrier}',
	 * )
	 * 
	 * @param array $templateVariables
	 * @return MailMessage
	 */
	public function setTemplateVariables(array $templateVariables){
		$this->templateVariables = $templateVariables;
		return $this;
	}
	
	/**
	 * Adds the given template variable. 
	 * 
	 * Sample Name: {delivery_company}
	 * Sample Value: Some Name of a Company
	 * 
	 * The value should be also a string.
	 * 
	 * @param string $name
	 * @param string $value
	 * @return MailMessage
	 */
	public function addTemplateVariable($name, $value) {
		$this->templateVariables[$name] = $value;
		return $this;
	}

	/**
	 * Returns the mail address to which the mail is 
	 * sent to.
	 * 
	 * @return string
	 */
	public function getToEmailAddress(){
		return $this->toEmailAddress;
	}

	/**
	 * Sets the mail address to which the mail is sent to.
	 * 
	 * @param string $toEmailAddress
	 * @return MailMessage
	 */
	public function setToEmailAddress($toEmailAddress){
		$this->toEmailAddress = $toEmailAddress;
		return $this;
	}

	/**
	 * Returns the name to which the mail is sent to. E.g. the 
	 * customer name in case of the order confirmation.
	 * 
	 * @return string
	 */
	public function getToName(){
		return $this->toName;
	}

	/**
	 * Sets the name to which the mail is sent to. E.g. the customer
	 * name in case of the order confirmation.
	 * 
	 * @param string $toName
	 * @return MailMessage
	 */
	public function setToName($toName){
		$this->toName = $toName;
		return $this;
	}

	/**
	 * Returns the e-mail address of the sender. In most cases
	 * this is the store e-mail address.
	 * 
	 * @return string
	 */
	public function getFromEmailAddress(){
		return $this->fromEmailAddress;
	}

	/**
	 * Sets the e-mail address of the sender. In most cases
	 * this is the store e-mail address.
	 * 
	 * @param string $fromEmailAddress
	 * @return MailMessage
	 */
	public function setFromEmailAddress($fromEmailAddress){
		$this->fromEmailAddress = $fromEmailAddress;
		return $this;
	}

	/**
	 * Returns the name of the sender. 
	 * 
	 * @return string
	 */
	public function getFromName(){
		return $this->fromName;
	}

	/**
	 * Sets the name of the sender.
	 * 
	 * @param string $fromName
	 * @return MailMessage
	 */
	public function setFromName($fromName){
		$this->fromName = $fromName;
		return $this;
	}

	/**
	 * Returns the file attachment of the mail. See MailMessageAttachment for more
	 * information about the format etc.
	 * 
	 * @return MailMessageAttachment
	 */
	public function getFileAttachment(){
		return $this->fileAttachment;
	}

	/**
	 * Sets the mail attachment.
	 * 
	 * @param MailMessageAttachment $fileAttachment
	 * @return MailMessage
	 */
	public function setFileAttachment(MailMessageAttachment $fileAttachment){
		$this->fileAttachment = $fileAttachment;
		return $this;
	}

	/**
	 * Sets the path in which the mail template is searched. By default it points 
	 * to _PS_MAIL_DIR_.
	 * 
	 * @return string
	 */
	public function getTemplateFolderPath(){
		return $this->templateFolderPath;
	}

	/**
	 * Sets the path in which the mail template is searched. By default it points
	 * to _PS_MAIL_DIR_.
	 * 
	 * @param string $templateFolderPath
	 * @return MailMessage
	 */
	public function setTemplateFolderPath($templateFolderPath){
		$this->templateFolderPath = $templateFolderPath;
		return $this;
	}


	/**
	 * Gets the SMTP mode.
	 * 
	 * @return string
	 */
	public function getModeSMTP(){
		return $this->modeSmtp;
	}

	/**
	 * Sets the SMTP mode.
	 * 
	 * @param string $modeSmtp
	 * @return MailMessage
	 */
	public function setModeSMTP($modeSmtp){
		$this->modeSmtp = $modeSmtp;
		return $this;
	}


	/**
	 * Returns the shop id.
	 * 
	 * @return int
	 */
	public function getShopId(){
		return $this->shopId;
	}

	/**
	 * Sets the shop id.
	 * 
	 * @param int $shopId
	 * @return MailMessage
	 */
	public function setShopId($shopId){
		$this->shopId = $shopId;
		return $this;
	}
	
	/**
	 * Returns the BCC of the message.
	 * 
	 * @return string
	 */
	public function getBcc() {
		return $this->bcc;
	}
	
	/**
	 * Sets the BCC of the message.
	 * 
	 * @param string $bcc
	 * @return MailMessage
	 */
	public function setBcc($bcc) {
		$this->bcc = $bcc;
		return $this;
	}
	
	/**
	 * Returns the reply to header of the e-mail message.
	 * 
	 * @return the reply to header of the e-mail message.
	 */
	public function getReplyTo() {
		return $this->replyTo;
	}
	
	/**
	 * Sets the reply to header of the e-mail message.
	 * 
	 * @param string $replyTo the reply to header of the e-mail message.
	 * @return MailMessage this message object.
	 */
	public function setReplyTo($replyTo) {
		$this->replyTo = $replyTo;
		return $this;
	}
	
	/**
	 * Returns the name of the reply to receipient.
	 * 
	 * @return string 
	 */
	public function getReplyToName() {
		return $this->replyToName;
	}
	
	/**
	 * Sets the name of the reply to recipient.
	 * 
	 * @param string $replyToName the name of the reply to receipient.
	 * @return MailMessage this message object.
	 */
	public function setReplyToName($replyToName) {
		$this->replyToName = $replyToName;
		return $this;
	}
	
}