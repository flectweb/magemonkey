<?php

class Ebizmarts_MageMonkey_Model_Observer
{
	/**
	 * Handle Subscriber object saving process
	 */
	public function handleSubscriber(Varien_Event_Observer $observer)
	{

		if( TRUE === Mage::helper('monkey')->isWebhookRequest()){
			return $observer;
		}

		$subscriber = $observer->getEvent()->getSubscriber();

		$subscriber->setImportMode(TRUE);

		$email  = $subscriber->getSubscriberEmail();
		$listId = Mage::helper('monkey')->getDefaultList($subscriber->getStoreId());
		$isConfirmNeed = (Mage::getStoreConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, $subscriber->getStoreId()) == 1) ? TRUE : FALSE;

		$doubleoptin = ((int)Mage::helper('monkey')->config('double_optin', $subscriber->getStoreId()) === 1) ? TRUE : FALSE;

		//New subscriber, just add
		if( $subscriber->isObjectNew() ){

			if(FALSE === $isConfirmNeed){

				if( TRUE === $doubleoptin ){
					$subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE);
				}
				Mage::getSingleton('monkey/api')
									->listSubscribe($listId, $email, NULL, 'html', $doubleoptin);

			}

		}else{

			$status    = (int)$subscriber->getData('subscriber_status');
			$oldstatus = (int)$subscriber->getOrigData('subscriber_status');

			if( $status !== $oldstatus ){ //Status change

				//Unsubscribe customer
				if($status == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED){

					Mage::getSingleton('monkey/api')
									->listUnsubscribe($listId, $email);

				}else if($status == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED){

					if( FALSE === $isConfirmNeed || ($oldstatus == Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE) ){
						Mage::getSingleton('monkey/api')
									->listSubscribe($listId, $email, NULL, 'html', $doubleoptin);
					}

				}

			}

		}
	}

	/**
	 * Handle Subscriber deletion from Magento, unsubcribes email from MailChimp
	 * and sends the delete_member flag so the subscriber gets deleted.
	 */
	public function handleSubscriberDeletion(Varien_Event_Observer $observer)
	{
		if( TRUE === Mage::helper('monkey')->isWebhookRequest()){
			return $observer;
		}

		$subscriber = $observer->getEvent()->getSubscriber();
		$subscriber->setImportMode(TRUE);

		$listId = Mage::helper('monkey')->getDefaultList($subscriber->getStoreId());

		Mage::getSingleton('monkey/api', array('store' => $subscriber->getStoreId()))
									->listUnsubscribe($listId, $subscriber->getSubscriberEmail(), TRUE);

	}
}