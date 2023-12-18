<?php   
/* CAT:Pie charts */

/* pChart library inclusions */
require_once("bootstrap.php");
require_once("functions.inc.php");

use pChart\pColor;
use pChart\pDraw;
use pChart\pPie;

/* Create the pChart object */
$myPicture = new pDraw(300,260);

/* Populate the pData object */
$myPicture->myData->addPoints([50,2,3,4,7,10,25,48,41,10],"ScoreA");
$myPicture->myData->setSerieDescription("ScoreA","Application A");

/* Define the abscissa serie */
$myPicture->myData->addPoints(["A0","B1","C2","D3","E4","F5","G6","H7","I8","J9"],"Labels");
$myPicture->myData->setAbscissa("Labels");

/* Draw a solid background */
$myPicture->drawFilledRectangle(0,0,300,300,["Color"=>new pColor(170,183,87), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);

/* Overlay with a gradient */
$myPicture->drawGradientArea(0,0,300,260, DIRECTION_VERTICAL,["StartColor"=>new pColor(219,231,139,50),"EndColor"=>new pColor(1,138,68,50)]);
$myPicture->drawGradientArea(0,0,300,20, DIRECTION_VERTICAL, ["StartColor"=>ColorBlack(), "EndColor"=>new pColor(50)]);

/* Add a border to the picture */
$myPicture->drawRectangle(0,0,299,259,["Color"=>ColorBlack()]);

/* Write the picture title */ 
$myPicture->setFontProperties(["FontName"=>"fonts/PressStart2P-Regular.ttf","FontSize"=>6]);
$myPicture->drawText(10,15,"pPie - Draw 2D ring charts",["Color"=>ColorWhite()]);

/* Set the default font properties */ 
$myPicture->setFontProperties(["FontName"=>"fonts/Cairo-Regular.ttf","FontSize"=>9,"Color"=>new pColor(80)]);

/* Enable shadow computing */ 
$myPicture->setShadow(TRUE,["X"=>2,"Y"=>2,"Color"=>ColorBlack($Alpha=50)]);

/* Create the pPie object */ 
$PieChart = new pPie($myPicture);

/* Draw an AA pie chart */ 
$PieChart->draw2DRing(160,140,["WriteValues"=>TRUE,"ValueColor"=>ColorWhite(),"Border"=>TRUE]);

/* Write the legend box */ 
$myPicture->setShadow(FALSE);
$PieChart->drawPieLegend(15,40,["Color"=>new pColor(200,200,200,20)]);

/* Render the picture (choose the best way) */
$myPicture->autoOutput("temp/example.draw2DRingValue.png");

