<?php

class WikidataPageHistory extends HistoryAction {

	public function onView() {
		global $wdHandlerClasses;
		$ns = $this->getTitle()->getNamespace();
		$handlerClass = $wdHandlerClasses[ $ns ];
		$handlerInstance = new $handlerClass( $this->getTitle() );
		$handlerInstance->history();
	}

}
