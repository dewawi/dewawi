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
$myPicture->drawText(10,15,"drawFromJPG() - add pictures to your charts",["Color"=>new pColor(255)]);

/* Turn off shadow computing */ 
$myPicture->setShadow(FALSE);

/* Draw a JPG object */
$myPicture->drawFromJPG(100,45,"examples/resources/landscape1.jpg");

/* Turn on shadow computing */ 
$myPicture->setShadow(TRUE,["X"=>2,"Y"=>2,"Color"=>new pColor(0,0,0,20)]);

/* Draw a JPG object */
$myPicture->drawFromJPG(380,45,"examples/resources/landscape2.jpg");

/* Write the legend */
$myPicture->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,20)]);
$TextSettings = ["Color"=>new pColor(255),"FontSize"=>10,"FontName"=>"fonts/Abel-Regular.ttf","Align"=>TEXT_ALIGN_BOTTOMMIDDLE];
$myPicture->drawText(220,210,"Without shadow",$TextSettings);
$myPicture->drawText(490,210,"With enhanced shadow",$TextSettings);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.drawFromJPG.png");

