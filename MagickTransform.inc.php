<?php

class MagickTransform {

    function actionable($imgPath) {
        $wand = NewMagickWand();
        $out = MagickReadImage($wand, $imgPath);
        DestroyMagickWand($wand);

        return $out;
    }

    function transformForWeb($imgPath, $size = 650) {
        $wand = NewMagickWand();
        MagickReadImage($wand, $imgPath);
        $this->imgScale($wand, $size);
        MagickSetFormat($wand, "png");
        $newName = (MagickGetImageFilename($wand) ) ? MagickGetImageFilename($wand) . '-web' : false;
        ob_start();
        $magickBlob = MagickEchoImagesBlob($wand);
        ob_end_clean();
        if ($newName) {
            if (!file_put_contents($newName, $magickBlob))
                return false;
        }
        DestroyMagickWand($wand);
        return $newName;
    }

    function transformForHighRes($imgPath) {
        $wand = NewMagickWand();
        MagickReadImage($wand, $imgPath);
        MagickSetFormat($wand, "png");
        $newName = (MagickGetImageFilename($wand) ) ? MagickGetImageFilename($wand) . '-raw' : false;
        ob_start();
        $magickBlob = MagickEchoImagesBlob($wand);
        ob_end_clean();
        if ($newName) {
            if (!file_put_contents($newName, $magickBlob))
                return false;
        }
        DestroyMagickWand($wand);
        return $newName;
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
        
        return $this->imgSize($wand, $newWandSize);
    }

    /**
     * imgSize - Resizes an image to specified dimensions
     * @param magickwand object $wand
     * @param image geometry string $newWandSize
     * @return boolean 
     */
    function imgSize(&$wand, $newWandSize) {

        if (MagickResizeImage($wand, $newWandSize[0], $newWandSize[1], MW_GaussianFilter, 0)) {
            $transformedSize = array(MagickGetImageWidth($wand), MagickGetImageHeight($wand));
            return $transformedSize;
        }
    }

}