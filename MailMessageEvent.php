<?php
if (! defined('_PS_VERSION_')){
	exit();
}
/**
 * This class represents a mail message sending event. An object
 * of this class is propagated in case an e-mail is sent.
 * 
 * @author Thomas Hunziker
 *
 */
class MailMessageEvent {

	/**
	 * @var string
	 */
	private $name;
	
	/**
	 * @var boolean
	 */
	private $die = false;
	
	/**
	 * @var MailMessage[]
	 */
	private $messages = array();
	
	/**
	 * Constructor.
	 * 
	 * The name is used as identification of the event. It is normally 
	 * the name of the template. 
	 * 
	 * @param string $name
	 */
	public function __construct($name) {
		$this->name = (string)$name;
	}
	
	/**
	 * In case this method returns true, any error will stop the PHP 
	 * process. Otherwise in case of an error the mail is not sent and 
	 * the error is logged. But the process continues.
	 * 
	 * By default this method returns false.
	 *
	 * @return boolean
	 */
	public function isDie(){
		return $this->die;
	}
	
	/**
	 * Sets the behaviour in case of an error. See MailMessageEvent::isDie() for
	 * more information.
	 * 
	 * By default it is set to false. In most cases this should not be changed.
	 * 
	 * @param string $die
	 * @return MailMessageEvent
	 */
	public function setDie($die = true){
		$this->die = $die;
		return $this;
	}

	/**
	 * Returns the name of the e-mail event. This is the original template name of the
	 * e-mail. E.g. order_conf in case of the order confirmation. The name 
	 * should be used to identify the event. For example if something should be changed
	 * in case of order confirmation the hook should filter with the expression:
	 * 
	 * <code>
	 * if ($event->getName() == 'order_conf') {
	 *    // Change messages
	 * }
	 * </code>
	 * 
	 * @return string
	 */
	public function getName(){
		return $this->name;
	}

	/**
	 * Returns a list of MailMessage send by this event. By default the list contains
	 * exaclty one message. In case the hook whats to suppress the sending of the message,
	 * the list should be set to an empty array. In case more than one message should be send
	 * more messages can be added.
	 * 
	 * @return MailMessage[]
	 */
	public function getMessages(){
		return $this->messages;
	}

	/**
	 * Returns the list of messages sent by the event. By default the list contains
	 * one message. Listeners of the event may add more messages or remove all message, which 
	 * does prevent sending any mail message.
	 * 
	 * @param MailMessage[] $messages
	 * @return MailMessageEvent
	 */
	public function setMessages(array $messages){
		$this->messages = $messages;
		return $this;
	}
	
	/**
	 * Adds the given message to the event. See MailMessageEvent::setMessages() for more information
	 * about the behaviour of multiple messages.
	 * 
	 * @param MailMessage $message
	 * @return MailMessageEvent
	 */
	public function addMessage(MailMessage $message) {
		$this->messages[] = $message;
		return $this;
	}
	
	
}