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
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL, ["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);
$myPicture->drawGradientArea(0,0,700,20, DIRECTION_VERTICAL, ["StartColor"=>new pColor(0,0,0,80),"EndColor"=>new pColor(50,50,50,80)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,699,229,["Color"=>new pColor(0)]);

/* Write the picture title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/PressStart2P-Regular.ttf","FontSize"=>6]);
$myPicture->drawText(10,15,"drawLine() - Basis",["Color"=>new pColor(255)]);

/* Turn on shadow computing */ 
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,20)]);

/* Draw some lines */ 
for($i=1;$i<=100;$i=$i+4)
{
	/* new pColor() == get random color */
	$myPicture->drawLine($i+5, 215, $i*7+5, 30, ["Color"=>new pColor(),"Ticks"=>rand(0,4)]);
}

/* Draw an horizontal dashed line with extra weight */
$myPicture->drawLine(370,160,650,160,["Color"=>new pColor(0),"Ticks"=>4,"Weight"=>3]);

/* Another example of extra weight */
$myPicture->drawLine(370,180,650,200,["Color"=>new pColor(255),"Ticks"=>15,"Weight"=>1]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawLine.png");

