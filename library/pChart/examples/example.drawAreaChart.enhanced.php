<?php   
/* CAT:Area Chart */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;
use pChart\pCharts;

/* Create the pChart object */
$myPicture = new pDraw(700,230);

/* Populate the pData object */
$myPicture->myData->addPoints([4,2,10,12,8,3],"Probe 1");
$myPicture->myData->addPoints([3,12,15,8,5,5],"Probe 2");
$myPicture->myData->addPoints([2,7,5,18,15,22],"Probe 3");
$myPicture->myData->setSerieProperties("Probe 2",["Ticks" => 4]);
$myPicture->myData->setAxisName(0,"Temperatures");
$myPicture->myData->addPoints(["Jan","Feb","Mar","Apr","May","Jun"],"Labels");
$myPicture->myData->setSerieDescription("Labels","Months");
$myPicture->myData->setAbscissa("Labels");

/* Turn off Anti-aliasing */
$myPicture->setAntialias(FALSE);

/* Draw the background */ 
$myPicture->drawFilledRectangle(0,0,700,230,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */ 
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL,["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,699,229,["Color"=>new pColor(0)]);

/* Write the chart title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>11]);
$myPicture->drawText(150,35,"Average temperature",["FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE]);

/* Set the default font */
$myPicture->setFontProperties(["FontSize"=>7]);

/* Define the chart area */
$myPicture->setGraphArea(60,40,650,200);

/* Draw the scale */
$myPicture->drawScale(["XMargin"=>10,"YMargin"=>10,"Floating"=>TRUE,"GridColor"=>new pColor(255),"DrawSubTicks"=>TRUE,"CycleBackground"=>TRUE]);

/* Write the chart legend */
$myPicture->drawLegend(540,20,["Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL]);

/* Turn on Anti-aliasing */
$myPicture->setAntialias(TRUE);

/* Create the pCharts object */
$pCharts = new pCharts($myPicture);

/* Draw the area chart */
$pCharts->drawAreaChart();

$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);

/* Draw a line and a plot chart on top */
$pCharts->drawLineChart();
$pCharts->drawPlotChart(["PlotBorder"=>TRUE,"PlotSize"=>3,"BorderSize"=>1,"Surrounding"=>-60,"BorderColor"=>new pColor(50,50,50,80)]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawAreaChart.simple.png");

