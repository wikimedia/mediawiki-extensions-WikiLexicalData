<?php

class WikidataPageHistory extends HistoryPage {

	public function history() {
  		wfProfileIn( __METHOD__ );
 
		global $wdHandlerClasses;
		$ns = $this->getTitle()->getNamespace();
		$handlerClass = $wdHandlerClasses[ $ns ];
		$handlerInstance = new $handlerClass( $this->getTitle() );
		$handlerInstance->history();

		wfProfileOut( __METHOD__ );
	}

}
