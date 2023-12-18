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
$myPicture->myData->addPoints([150,220,300,250,420,200,300,200,100],"Server A");
$myPicture->myData->addPoints([140,0,340,300,320,300,200,100,50],"Server B");
$myPicture->myData->setAxisName(0,"Hits");
$myPicture->myData->addPoints(["January","February","March","April","May","June","July","August","September"],"Months");
$myPicture->myData->setSerieDescription("Months","Month");
$myPicture->myData->setAbscissa("Months");

/* Create the floating 0 data serie */
$myPicture->myData->addPoints([60,80,20,40,0,50,90,30,100],"Floating 0");
$myPicture->myData->setSerieProperties("Floating 0",["isDrawable" => FALSE]);

/* Draw the background */
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL,  ["StartColor"=>new pColor(240),"EndColor"=>new pColor(180)]);
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_HORIZONTAL,["StartColor"=>new pColor(240,240,240,20), "EndColor"=>new pColor(180,180,180,20)]);
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>7]);

/* Draw the scale  */
$myPicture->setGraphArea(50,30,680,200);
$myPicture->drawScale(["CycleBackground"=>TRUE,"DrawSubTicks"=>TRUE,"GridColor"=>new pColor(0,0,0,10)]);

/* Turn on shadow computing */ 
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);

/* Draw the chart */
(new pCharts($myPicture))->drawBarChart([
	"Floating0Serie"=>"Floating 0",
	"Draw0Line"=>TRUE,
	"Gradient"=>TRUE,
	"DisplayPos"=>LABEL_POS_INSIDE,
	"DisplayValues"=>TRUE,
	"DisplayColor"=>new pColor(255),
	"DisplayShadow"=>TRUE,
	"Surrounding"=>10
]);

/* Write the chart legend */
$myPicture->drawLegend(580,12,["Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawBarChart.floating.png");

