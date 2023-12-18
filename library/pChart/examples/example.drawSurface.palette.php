<?php   
/* CAT:Surface chart */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\{
	pColor,
	pDraw,
	pSurface
};

/* Create the pChart object */
$myPicture = new pDraw(400,220);

/* Create a solid background */
$myPicture->drawFilledRectangle(0,0,400,220,["Color"=>new pColor(50,70,0),"Dash"=>TRUE, "DashColor"=>new pColor(30,50,0)]);

/* Do a gradient overlay */
$myPicture->drawGradientArea(0,0,400,220,DIRECTION_VERTICAL,["StartColor"=>new pColor(194,131,44,50), "EndColor"=>new pColor(43,7,58,50)]);
$myPicture->drawGradientArea(0,0,400,20, DIRECTION_VERTICAL,["StartColor"=>new pColor(0), "EndColor"=>new pColor(50)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,399,219,["Color"=>new pColor(0)]);

/* Write the picture title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/PressStart2P-Regular.ttf","FontSize"=>6]);
$myPicture->drawText(10,15,"pSurface() :: 2D surface charts",["Color"=>new pColor(255)]);

$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1]);

/* Create the surface object */
$mySurface = new pSurface($myPicture);

/* Set the grid size */
$mySurface->setGrid(200,0);

/* Write the axis labels */
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>6,"Color"=>new pColor(255)]);

/* Draw the surface chart */
$Palette = [
	new pColor(0),
	new pColor(29,243,119),
	new pColor(238,216,78),
	new pColor(246,45,53)
];

$myPicture->setGraphArea(40,40,380,80);
$mySurface->writeYLabels(["Labels"=>"1st Seq"]);
for($i=0; $i<=200; $i++) {
	$mySurface->addPoint($i,0,rand(0,3));
}
$mySurface->drawSurface(["Padding"=>0,"Palette"=>$Palette]);

$myPicture->setGraphArea(40,100,380,140);
$mySurface->writeYLabels(["Labels"=>"2nd Seq"]);
for($i=0; $i<=200; $i++) {
	$mySurface->addPoint($i,0,rand(0,3));
}
$mySurface->drawSurface(["Padding"=>0,"Palette"=>$Palette]);

$myPicture->setGraphArea(40,160,380,200);
$mySurface->writeYLabels(["Labels"=>"3rd Seq"]);
for($i=0; $i<=200; $i++) {
	$mySurface->addPoint($i,0,rand(0,3));
}
$mySurface->drawSurface(["Padding"=>0,"Palette"=>$Palette]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.surface.palette.png");

