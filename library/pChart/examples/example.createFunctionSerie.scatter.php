<?php   
/* CAT:Mathematical */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\{
	pColor,
	pDraw,
	pScatter
};

/* Create the pChart object */
$myPicture = new pDraw(400,400);

/* Create the X axis and the binded series */
$myPicture->myData->createFunctionSerie("X", function($z){return ($z == 0) ? VOID : 1/$z;},"1/z",["MinX"=>-10,"MaxX"=>10,"XStep"=>1]);
$myPicture->myData->setAxisProperties(0, ["Name" => "x = 1/z", "Identity" => AXIS_X, "Position" => AXIS_POSITION_BOTTOM]);

/* Create the Y axis */
$myPicture->myData->createFunctionSerie("Y", function($z){return $z;},"z",["MinX"=>-10,"MaxX"=>10,"XStep"=>1]);
$myPicture->myData->setSerieOnAxis("Y",1);
$myPicture->myData->setAxisProperties(1, ["Name" => "y = z", "Identity" => AXIS_Y, "Position" => AXIS_POSITION_RIGHT]);

/* Create the Y axis */
$myPicture->myData->createFunctionSerie("Y2", function($z){return $z*$z*$z;},"z*z*z",["MinX"=>-10,"MaxX"=>10,"XStep"=>1]);
$myPicture->myData->setSerieOnAxis("Y2",2);
$myPicture->myData->setAxisProperties(2, ["Name" => "y = z*z*z", "Identity" => AXIS_Y, "Position" => AXIS_POSITION_LEFT]);

/* Create the 1st scatter chart binding */
$myPicture->myData->setScatterSerie("X","Y",0);
$myPicture->myData->setScatterSerieProperties(0, ["Description" => "Pass A", "Color" => new pColor(0), "Ticks" => 4]);

/* Create the 2nd scatter chart binding */
$myPicture->myData->setScatterSerie("X","Y2",1);
$myPicture->myData->setScatterSerieProperties(1, ["Description" => "Pass B", "Color" => new pColor(120,0,255), "Ticks" => 4]);

/* Draw the background */
$myPicture->drawFilledRectangle(0,0,400,400,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */
$myPicture->drawGradientArea(0,0,400,400, DIRECTION_VERTICAL, ["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);
$myPicture->drawGradientArea(0,0,400,20,  DIRECTION_VERTICAL, ["StartColor"=>new pColor(0,0,0,80),"EndColor"=>new pColor(50,50,50,80)]);

/* Write the picture title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/PressStart2P-Regular.ttf","FontSize"=>6]);
$myPicture->drawText(10,15,"createFunctionSerie() - Functions computing",["Color"=>new pColor(255)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,399,399,["Color"=>new pColor(0)]);

/* Set the default font */
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>7]);

/* Set the graph area */
$myPicture->setGraphArea(50,50,350,350);

/* Create the Scatter chart object */
$myScatter = new pScatter($myPicture);

/* Draw the scale */
$myScatter->drawScatterScale(["XMargin"=>10,"YMargin"=>10,"Floating"=>TRUE]);

/* Turn on shadow computing */
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);

/* Draw the 0/0 lines */
$myScatter->drawScatterThreshold(0,["AxisID"=>0,"Color"=>new pColor(0),"Ticks"=>10]);
$myScatter->drawScatterThreshold(0,["AxisID"=>1,"Color"=>new pColor(0),"Ticks"=>10]);

/* Draw a threshold area */
$myScatter->drawScatterThresholdArea(-0.1,0.1,["AreaName"=>"Error zone"]); /* ["AreaName"=>NULL] */

/* Draw a scatter plot chart */
$myScatter->drawScatterLineChart();
$myScatter->drawScatterPlotChart();

/* Draw the legend */
$myScatter->drawScatterLegend(300,380,["Mode"=>LEGEND_HORIZONTAL,"Style"=>LEGEND_NOBORDER]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.createFunctionSerie.scatter.png");

