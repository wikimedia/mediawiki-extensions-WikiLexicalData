<?php

class WikidataEditPage extends EditPage {

	public function edit() {
		global $wdHandlerClasses;
		$ns = $this->mTitle->getNamespace();
		$handlerClass = $wdHandlerClasses[ $ns ];
		$handlerInstance = new $handlerClass( $this->mTitle );
		$handlerInstance->edit();
	}

}
