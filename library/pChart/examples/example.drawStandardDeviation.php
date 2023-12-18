<?php   
/* CAT:Mathematical */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;
use pChart\pCharts;

/* Create the pChart object */
$myPicture = new pDraw(700,230);
$myPicture->myData = new Examples\pDataExt();

/* Populate the pData object */
$myPicture->myData->addRandomValues("Probe 1", ["Values"=>100,"Min"=>0,"Max"=>20]);
$myPicture->myData->setAxisName(0,"Temperatures");

/* Draw the background */
$myPicture->drawFilledRectangle(0,0,700,230,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL, ["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);

/* Turn off Anti-aliasing */
$myPicture->setAntialias(FALSE);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,699,229,["Color"=>new pColor(0)]);

/* Write the chart title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>9]);
$myPicture->drawText(140,35,"Measured temperature",["FontSize"=>14,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE]);
$myPicture->drawText(340,30,"(and associated standard deviation)",["FontSize"=>10,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE]);

/* Set the default font */
$myPicture->setFontProperties(["FontSize"=>7]);

/* Define the chart area */
$myPicture->setGraphArea(60,50,670,200);

/* Draw the scale */
$scaleSettings = ["LabelSkip"=>9,"GridColor"=>new pColor(200),"DrawSubTicks"=>TRUE,"CycleBackground"=>TRUE];
$myPicture->drawScale($scaleSettings);

/* Turn on Anti-aliasing */
$myPicture->setAntialias(TRUE);
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);

/* Draw the line chart */
(new pCharts($myPicture))->drawPlotChart(["PlotSize"=>2]);

/* Compute the serie average and standard deviation */ 
$Average = $myPicture->myData->getSerieAverage("Probe 1");

/* Compute the serie standard deviation */ 
$StandardDeviation = $myPicture->myData->getStandardDeviation("Probe 1"); 

/* Draw a threshold area */
$myPicture->setShadow(FALSE);
$myPicture->drawThresholdArea($Average - $StandardDeviation, $Average + $StandardDeviation, ["Color"=>new pColor(100,100,200,10)]);
$myPicture->setShadow(TRUE);

/* Draw the serie average */
$myPicture->drawThreshold([$Average],["WriteCaption"=>TRUE,"Caption"=>"Average value","AxisID"=>0]);

/* Draw the standard deviation boundaries */
$ThresholdSettings = ["WriteCaption"=>TRUE,"CaptionAlign"=>CAPTION_RIGHT_BOTTOM ,"Caption"=>"SD","AxisID"=>0,"Color"=>new pColor(0)];
$myPicture->drawThreshold([$Average+$StandardDeviation],$ThresholdSettings);
$myPicture->drawThreshold([$Average-$StandardDeviation],$ThresholdSettings);

/* Write the coefficient of variation */
$CoefficientOfVariation = round($myPicture->myData->getCoefficientOfVariation("Probe 1"),1);

$myPicture->drawText(610,46,"coefficient of variation : ".$CoefficientOfVariation,["Align"=>TEXT_ALIGN_BOTTOMMIDDLE]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawStandardDeviation.png");

