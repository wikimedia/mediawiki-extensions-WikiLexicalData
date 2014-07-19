<?php

class WikidataPageHistory extends HistoryAction {

	public function onView() {
  		wfProfileIn( __METHOD__ );
 
		global $wdHandlerClasses;
		$ns = $this->getTitle()->getNamespace();
		$handlerClass = $wdHandlerClasses[ $ns ];
		$handlerInstance = new $handlerClass( $this->getTitle() );
		$handlerInstance->history();

		wfProfileOut( __METHOD__ );
	}

}
