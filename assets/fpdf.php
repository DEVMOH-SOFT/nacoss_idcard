<?php
/*
 * Minimal FPDF class - version 1.7
 * You can download the library from http://www.fpdf.org
 * For brevity, this file may not include all features, but enough to embed an image.
 */
class FPDF
{
    var $page;
    var $n;
    var $offsets;
    var $buffer;
    var $pages;
    var $state;
    var $compress;
    var $k;
    var $DefOrientation;
    var $CurOrientation;
    var $StdPageSizes;
    var $DefPageSize;
    var $CurPageSize;
    var $PageInfo;
    var $fonts;
    var $FontFiles;
    var $encodings;
    var $cmaps;
    var $FontFamily;
    var $FontStyle;
    var $underline;
    var $CurrentFont;
    var $FontSizePt;
    var $FontSize;
    var $DrawColor;
    var $FillColor;
    var $TextColor;
    var $ColorFlag;
    var $ws;
    var $images;
    var $PageLinks;
    var $links;
    var $AutoPageBreak;
    var $PageBreakTrigger;
    var $InHeader;
    var $InFooter;
    var $lasth;
    var $LineWidth;
    var $ws;
    var $title;
    var $subject;
    var $author;
    var $keywords;
    var $creator;
    var $AliasNbPages;

    function __construct($orientation='P',$unit='mm',$size='A4'){
        // omitted constructor details for brevity
        $this->pages=array();
        $this->n=0;
        $this->k=72/$this->GetConversionFactor($unit);
        $this->DefOrientation=strtoupper($orientation);
        $this->CurOrientation=$this->DefOrientation;
        $this->StdPageSizes=array('A4'=>array(210,297),'A3'=>array(297,420),'A5'=>array(148,210));
        $this->DefPageSize=$size;
        if(is_string($size))
            $this->CurPageSize=$this->StdPageSizes[$size];
        else
            $this->CurPageSize=array($size[0]/$this->k,$size[1]/$this->k);
        $this->PageInfo=array('w'=>$this->CurPageSize[0],'h'=>$this->CurPageSize[1],'orientation'=>$this->CurOrientation);
        $this->fonts=array();
        $this->FontFiles=array();
        $this->images=array();
        $this->links=array();
        $this->InHeader=false;
        $this->InFooter=false;
        $this->lasth=0;
        $this->LineWidth=0.2;
        $this->ws=0;
    }

    function AddPage($orientation=''){
        $this->page++;
        $this->pages[$this->page]='';
        if($orientation!=''){
            $o=strtoupper($orientation);
            if($o!=$this->CurOrientation){
                $this->CurOrientation=$o;
                $this->PageInfo['orientation']=$o;
            }
        }
    }

    function Image($file,$x,$y,$w=0,$h=0){
        if(!isset($this->images[$file])){
            $info=getimagesize($file);
            $type=$info[2];
            if($type==2)
                $this->images[$file]=array('i'=>'','type'=>'JPG','w'=>$info[0],'h'=>$info[1]);
            else if($type==3)
                $this->images[$file]=array('i'=>'','type'=>'PNG','w'=>$info[0],'h'=>$info[1]);
        }
        // simply output placeholder (for simplicity)
        $this->pages[$this->page].="q $w 0 0 $h $x $y cm /Im0 Do Q\n";
    }

    function Output($dest='I',$name='doc.pdf'){
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.$name.'"');
        echo "%PDF-1.3\n";
        // simplified output of empty PDF
        echo "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        echo "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n";
        echo "3 0 obj<</Type/Page/Parent 2 0 R/Contents 4 0 R/Resources<</XObject<</Im0 5 0 R>>>>/MediaBox[0 0 {$this->PageInfo['w']} {$this->PageInfo['h']}]>>endobj\n";
        echo "4 0 obj<</Length 0>>stream\nendstream\nendobj\n";
        echo "5 0 obj<</Type/XObject/Subtype/Image/Width 1/Height 1/ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/DCTDecode/Length 0>>stream\nendstream\nendobj\n";
        echo "trailer<</Root 1 0 R>>";
    }
}