<?php

    require_once( "../server/bootstrap.php" );

	// Get top 100 rank
	UITools::setRankByProblemsSolved($smarty, 0, 100);

    $smarty->display( '../templates/rank.tpl' );