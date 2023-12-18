<?php   
/* CAT:Stacked chart */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;
use pChart\pCharts;

/* Create the pChart object */
$myPicture = new pDraw(700,230);

/* Populate the pData object */ 
$myPicture->myData->addPoints([20,40,65,100,70,55,40,22,12],"Male");
$myPicture->myData->addPoints([-22,-44,-61,-123,-74,-60,-52,-34,-21],"Female");
$myPicture->myData->addPoints(["0-10","10-20","20-30","30-40","40-50","50-60","60-70","70-80","80-90"],"Labels");
$myPicture->myData->setSerieDescription("Labels","Ages");
$myPicture->myData->setAbscissa("Labels");
$myPicture->myData->setAxisProperties(0, [
	"Name" => "Community members",
	"Display" => AXIS_FORMAT_CUSTOM,
	"Format" => function($Value){
		return abs($Value);
	}
]);

$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL,  ["StartColor"=>new pColor(240), "EndColor"=>new pColor(180)]);
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_HORIZONTAL,["StartColor"=>new pColor(240), "EndColor"=>new pColor(180,180,180,20)]);

/* Set the default font properties */
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>7]);

/* Draw the scale and the chart */
$myPicture->setGraphArea(60,20,680,190);
$myPicture->drawScale(["DrawSubTicks"=>TRUE,"Mode"=>SCALE_MODE_ADDALL]);

(new pCharts($myPicture))->drawStackedBarChart(["DisplayValues"=>TRUE,"DisplayType"=>DISPLAY_AUTO,"Gradient"=>TRUE,"Surrounding"=>-20,"InnerSurrounding"=>20]);

/* Write the chart legend */
$myPicture->drawLegend(600,210,["Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawStackedBarChart.pyramid.png");

