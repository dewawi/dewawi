<?php   
/* CAT:Line chart */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;
use pChart\pCharts;

/* Create the pChart object */
$myPicture = new pDraw(700,230);

/* Populate the pData object */
$myPicture->myData->addPoints([3,12,15,8,5,-5],"Probe 1");
$myPicture->myData->addPoints([2,7,5,18,19,22],"Probe 2");
$myPicture->myData->setSerieProperties("Probe 1", ["Weight" => 2]);
$myPicture->myData->setSerieProperties("Probe 2", ["Ticks" => 4]);
$myPicture->myData->setAxisName(0,"Temperatures");
$myPicture->myData->addPoints(["Jan","Feb","Mar","Apr","May","Jun"],"Labels");
$myPicture->myData->setSerieDescription("Labels","Months");
$myPicture->myData->setAbscissa("Labels");

/* Turn off Anti-aliasing */
$myPicture->setAntialias(FALSE);

/* Draw the background */
$myPicture->drawFilledRectangle(0,0,700,230,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL, ["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);
$myPicture->drawGradientArea(0,0,700,20, DIRECTION_VERTICAL, ["StartColor"=>new pColor(0,0,0,80),"EndColor"=>new pColor(50,50,50,80)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,699,229,["Color"=>new pColor(0)]);

/* Write the chart title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>8,"Color"=>new pColor(255)]);
$myPicture->drawText(10,18,"Average recorded temperature",["FontSize"=>11,"Align"=>TEXT_ALIGN_BOTTOMLEFT]);

/* Set the default font */
$myPicture->setFontProperties(["FontSize"=>7,"Color"=>new pColor(0)]);

/* Define the chart area */
$myPicture->setGraphArea(60,40,650,200);

/* Draw the scale */
$myPicture->drawScale(["XMargin"=>10,"YMargin"=>10,"Floating"=>TRUE,"GridColor"=>new pColor(200),"DrawSubTicks"=>TRUE,"CycleBackground"=>TRUE]);

/* Turn on Anti-aliasing */
$myPicture->setAntialias(TRUE);

/* Enable shadow computing */
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);

/* Create the pCharts object */
$pCharts = new pCharts($myPicture);

/* Draw the line chart */
$pCharts->drawLineChart();
$pCharts->drawPlotChart(["DisplayValues"=>TRUE,"PlotBorder"=>TRUE,"BorderSize"=>2,"Surrounding"=>-60,"BorderColor"=>new pColor(0,0,0,80)]);

/* Write the chart legend */
$myPicture->drawLegend(590,9,["Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL,"FontColor"=>new pColor(255)]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawLineChart.plots.png");

