<?php

class ImageTransmogrify {

    function actionable($imgPath) {
    	$wand = NewMagickWand();
    	$out = MagickReadImage($wand, $imgPath);
    	DestroyMagickWand($wand);
    	
    	return $out;
    	}

    function transformForWeb($imgPath, $size = 650) {
        $wand = NewMagickWand();
        $fileInfo = pathinfo($imgPath);

        MagickReadImage($wand, $imgPath);
        $wand = $this->imgScale($wand, $size);
        
        MagickSetFormat($wand, "png");
        $name = $this->newName($wand, $fileInfo['filename'] . '-web', dirname($imgPath));
        DestroyMagickWand($wand);
        return $name;
    }

    function transformForHighRes($imgPath) {
        $wand = NewMagickWand();
        $fileInfo = pathinfo($imgPath);
        
        MagickReadImage($wand, $imgPath);
        MagickSetFormat($wand, "png");
        $name = $this->newName($wand, $fileInfo['filename'] . '-raw', dirname($imgPath));
        DestroyMagickWand($wand);
        return $name;
    }

    function newName(&$wand, $filename, $dest) {
    	
    	$filename = str_replace("-raw", "", $filename);
    	
        $magickNewName = $filename . '.' . MagickGetFormat($wand);
        
        if (MagickWriteImage($wand, $dest . '/' . $magickNewName))
            return $magickNewName;
    }

    /**
     * imgScale - Scales an image proportionally
     * 
     * @param type $wand
     * @param type $maxWandSize
     * @return boolean 
     */
    function imgScale(&$wand, $maxWandSize) {
        $wandSize = array(MagickGetImageWidth($wand), MagickGetImageHeight($wand));
        $ratio = $wandSize[0] / $wandSize[1];
        $newWandSize = ($wandSize[0] >= $wandSize[1] ) ?
                array($maxWandSize, $wandSize[1] / $ratio) :
                array($wandSize[0] / $ratio, $maxWandSize);
        return MagickTransformImage($wand, '0x0', $newWandSize[0] . 'x' . $newWandSize[1]);
    }

}