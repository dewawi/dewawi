<?php   
/* CAT:Bar Chart */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;
use pChart\pCharts;

/* Create the pChart object */
$myPicture = new pDraw(700,230);

$palette_blind = [
	[109,152,171,100],
	[0,39,94,100],
	[254,183,41,100],
	[168,177,184,100],
	[255,255,255,100],
	[0,0,0,100]
];

/* Populate the pData object */
$myPicture->myData->loadPalette($palette_blind, $overwrite=TRUE);
$myPicture->myData->addPoints([150,220,300,250,420,200,300,200,110],"Server A");
$myPicture->myData->addPoints(["January","February","March","April","May","June","July","August","September"],"Months");
$myPicture->myData->setSerieDescription("Months","Month");
$myPicture->myData->setAbscissa("Months");

/* Create the floating 0 data serie */
$myPicture->myData->addPoints([60,80,20,40,40,50,90,30,100],"Floating 0");
$myPicture->myData->setSerieProperties("Floating 0",["isDrawable" => FALSE]);

/* Set the default font */
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>10,"Color"=>new pColor(110)]);

/* Write the title */
$myPicture->drawText(10,15,"Net Income 2k8");

/* Set the graphical area  */
$myPicture->setGraphArea(50,30,680,180);

/* Draw the scale  */
$AxisBoundaries = [0=>array("Min"=>0,"Max"=>500)];
$myPicture->drawScale([
	"InnerTickWidth"=>0,
	"OuterTickWidth"=>0,
	"Mode"=>SCALE_MODE_MANUAL,
	"ManualScale"=>$AxisBoundaries,
	"LabelRotation"=>45,
	"DrawXLines"=>FALSE,
	"GridColor"=>new pColor(0,0,0,30),
	"GridTicks"=>0,
	"AxisColor"=>new pColor(0,0,0,50),
	"RemoveYAxis" => TRUE
]);

/* Turn on shadow computing */ 
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);

/* Draw the chart */
(new pCharts($myPicture))->drawBarChart(["Floating0Serie"=>"Floating 0","Surrounding"=>10]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawBarChart.span.png");

