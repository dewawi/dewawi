<?php   
/* CAT:Drawing */

/* pChart library inclusions */
require_once("bootstrap.php");

use pChart\pColor;
use pChart\pDraw;

/* Create the pChart object */
$myPicture = new pDraw(700,230);

/* Draw the background */
$myPicture->drawFilledRectangle(0,0,700,230,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL,["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);
$myPicture->drawGradientArea(0,0,700,20,DIRECTION_VERTICAL, ["StartColor"=>new pColor(0,0,0,80),"EndColor"=>new pColor(50,50,50,80)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,699,229,["Color"=>new pColor(0)]);

/* Write the picture title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/PressStart2P-Regular.ttf","FontSize"=>6]);
$myPicture->drawText(10,15,"drawFilledRectangle() - Transparency & colors",["Color"=>new pColor(255)]);

/* Turn on shadow computing */ 
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,20)]);

/* Draw a customized filled rectangle */ 
$myPicture->drawFilledRectangle(20,60,400,170,["Color"=>new pColor(150,200,170),"Dash"=>TRUE,"DashColor"=>new pColor(170,220,190),"BorderColor"=>new pColor(255)]);

/* Draw a customized filled rectangle */ 
$myPicture->drawFilledRectangle(30,30,200,200,	["Color"=>new pColor(209,134,27,30)]);
$myPicture->drawFilledRectangle(480,50,650,80,	["Color"=>new pColor(209,31,27), "Surrounding"=>30]);
$myPicture->drawFilledRectangle(480,90,650,120, ["Color"=>new pColor(209,125,27),"Surrounding"=>30]);
$myPicture->drawFilledRectangle(480,130,650,160,["Color"=>new pColor(209,198,27),"Surrounding"=>30,"Ticks"=>2]);
$myPicture->drawFilledRectangle(480,170,650,200,["Color"=>new pColor(134,209,27),"Surrounding"=>30,"Ticks"=>2]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawFilledRectangle.png");

