<?php

class WikidataArticle extends Article {

	public function view() {
		global $wdHandlerClasses;
		$ns = $this->mTitle->getNamespace();
		$handlerClass = $wdHandlerClasses[ $ns ];
		$handlerInstance = new $handlerClass( $this->mTitle );
		$this->showRedirectedFromHeader();
		$handlerInstance->view();
	}

}
