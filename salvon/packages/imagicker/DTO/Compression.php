<?php

namespace Salvon\Imagicker\DTO;

enum Compression: int
{
    case UNDEFINED = 0;
    case NO = 1;
    case BZIP = 2;
    case FAX = 6;
    case GROUP4 = 7;
    case JPEG = 8;
    case JPEG2000 = 9;
    case LOSSLESSJPEG = 10;
    case LZW = 11;
    case RLE = 12;
    case ZIP = 13;
    case DXT1 = 3;
    case DXT3 = 4;
    case DXT5 = 5;
    case ZIPS = 14;
    case PIZ = 15;
    case PXR24 = 16;
    case B44 = 17;
    case B44A = 18;
    case LZMA = 19;
    case JBIG1 = 20;
    case JBIG2 = 21;
}
