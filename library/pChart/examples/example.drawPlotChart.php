<?php   
/* CAT:Plot chart */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;
use pChart\pCharts;

/* Create the pChart object */
$myPicture = new pDraw(700,230);

/* Populate the pData object */
$myPicture->myData->addPoints([-4,VOID,VOID,12,8,3],"Probe 1");
$myPicture->myData->addPoints([3,12,15,4,2,-5],"Probe 2");
$myPicture->myData->addPoints([2,7,5,18,19,22],"Probe 3");
$myPicture->myData->setAxisName(0,"Temperatures");
$myPicture->myData->addPoints(["Jan","Feb","Mar","Apr","May","Jun"],"Labels");
$myPicture->myData->setSerieDescription("Labels","Months");
$myPicture->myData->setAbscissa("Labels");

/* Draw the background */
$myPicture->drawFilledRectangle(0,0,700,230,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL, ["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);
$myPicture->drawGradientArea(0,0,700,20, DIRECTION_VERTICAL, ["StartColor"=>new pColor(0,0,0,80),"EndColor"=>new pColor(50,50,50,80)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,699,229,["Color"=>new pColor(0)]);

/* Write the picture title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/PressStart2P-Regular.ttf","FontSize"=>6]);
$myPicture->drawText(10,15,"drawPlotChart() - draw a plot chart",["Color"=>new pColor(255)]);

/* Write the chart title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>9]);
$myPicture->drawText(250,55,"Average temperature",["FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE]);

/* Draw the scale and the 1st chart */
$myPicture->setGraphArea(60,60,450,190);
$myPicture->drawFilledRectangle(60,60,450,190,["Color"=>new pColor(255,255,255,10),"Surrounding"=>-200]);
$myPicture->drawScale(["DrawSubTicks"=>TRUE]);
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);
$myPicture->setFontProperties(["FontSize"=>7]);

/* Create the pCharts object */
$pCharts = new pCharts($myPicture);
$pCharts->drawPlotChart([
	"BorderSize"=>1,
	"Surrounding"=>40,
	"BorderColor"=>new pColor(50,50,50,100),
	"PlotSize"=>2,
	"PlotBorder"=>TRUE,
	"DisplayValues"=>TRUE,
	"DisplayType"=>DISPLAY_AUTO
]);

$myPicture->setShadow(FALSE);

/* Draw the scale and the 2nd chart */
$myPicture->setGraphArea(500,60,670,190);
$myPicture->drawFilledRectangle(500,60,670,190,["Color"=>new pColor(255,255,255,10),"Surrounding"=>-200]);
$myPicture->drawScale(["Pos"=>SCALE_POS_TOPBOTTOM,"DrawSubTicks"=>TRUE]);
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);

$pCharts->drawPlotChart(["PlotSize"=>1,"PlotBorder"=>TRUE,"BorderSize"=>1]);

$myPicture->setShadow(FALSE);

/* Write the chart legend */
$myPicture->drawLegend(510,205,["Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawPlotChart.png");

