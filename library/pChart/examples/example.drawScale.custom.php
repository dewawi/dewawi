<?php   
/* CAT:Scaling */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;

/* Create the pChart object */
$myPicture = new pDraw(700,230);

$YAxisFormat = function($Value){
	return round($Value/1000,2)."k"; 
};

$XAxisFormat = function($Value){
	return (($Value-1230768000)/(60*60*24))." day";
};

/* Populate the pData object */
$myPicture->myData->addPoints([1700,2500,7800,4500,3150],"Distance");
$myPicture->myData->setAxisProperties(0, ["Name" => "Maximum distance", "Unit" => "m", "Display" => AXIS_FORMAT_CUSTOM, "Format" => $YAxisFormat]);

/* Create the abscissa serie */
$myPicture->myData->addPoints([1230768000,1233446400,1235865600,1238544000,1241136000,1243814400],"Timestamp");
$myPicture->myData->setSerieDescription("Timestamp","Sampled Dates");
$myPicture->myData->setAbscissa("Timestamp", ["Name" => "Dates", "Display" => AXIS_FORMAT_CUSTOM, "Format" => $XAxisFormat]);

/* Draw the background */
$myPicture->drawFilledRectangle(0,0,700,230,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL, ["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);
$myPicture->drawGradientArea(0,0,700,20, DIRECTION_VERTICAL, ["StartColor"=>new pColor(0,0,0,80),"EndColor"=>new pColor(50,50,50,80)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,699,229,["Color"=>new pColor(0)]);

/* Write the picture title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/PressStart2P-Regular.ttf","FontSize"=>6]);
$myPicture->drawText(10,15,"drawScale() - draw the X-Y scales",["Color"=>new pColor(255)]);

/* Set the default font */
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>7]);

/* Draw the scale */
$myPicture->setGraphArea(60,60,660,190);
$myPicture->drawScale();
$myPicture->drawFilledRectangle(60,60,660,190,["Color"=>new pColor(255,255,255,10),"Surrounding"=>-200]);

/* Write the chart title */
$myPicture->setFontProperties(["FontSize"=>11]);
$myPicture->drawText(350,55,"My chart title",["FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMMIDDLE]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawScale.custom.png");

