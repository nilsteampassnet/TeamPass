<?php

declare(strict_types=1);

namespace CBOR;

use Stringable;

interface CBORObject extends Stringable
{
    public const MAJOR_TYPE_UNSIGNED_INTEGER = 0b000;

    public const MAJOR_TYPE_NEGATIVE_INTEGER = 0b001;

    public const MAJOR_TYPE_BYTE_STRING = 0b010;

    public const MAJOR_TYPE_TEXT_STRING = 0b011;

    public const MAJOR_TYPE_LIST = 0b100;

    public const MAJOR_TYPE_MAP = 0b101;

    public const MAJOR_TYPE_TAG = 0b110;

    public const MAJOR_TYPE_OTHER_TYPE = 0b111;

    public const LENGTH_1_BYTE = 0b00011000;

    public const LENGTH_2_BYTES = 0b00011001;

    public const LENGTH_4_BYTES = 0b00011010;

    public const LENGTH_8_BYTES = 0b00011011;

    public const LENGTH_INDEFINITE = 0b00011111;

    public const FUTURE_USE_1 = 0b00011100;

    public const FUTURE_USE_2 = 0b00011101;

    public const FUTURE_USE_3 = 0b00011110;

    public const OBJECT_FALSE = 20;

    public const OBJECT_TRUE = 21;

    public const OBJECT_NULL = 22;

    public const OBJECT_UNDEFINED = 23;

    public const OBJECT_SIMPLE_VALUE = 24;

    public const OBJECT_HALF_PRECISION_FLOAT = 25;

    public const OBJECT_SINGLE_PRECISION_FLOAT = 26;

    public const OBJECT_DOUBLE_PRECISION_FLOAT = 27;

    public const OBJECT_BREAK = 0b00011111;

    public const TAG_STANDARD_DATETIME = 0;

    public const TAG_EPOCH_DATETIME = 1;

    public const TAG_UNSIGNED_BIG_NUM = 2;

    public const TAG_NEGATIVE_BIG_NUM = 3;

    public const TAG_DECIMAL_FRACTION = 4;

    public const TAG_BIG_FLOAT = 5;

    public const TAG_COSE_ENCRYPT0 = 16;

    public const TAG_COSE_MAC0 = 17;

    public const TAG_COSE_SIGN1 = 18;

    public const TAG_ENCODED_BASE64_URL = 21;

    public const TAG_ENCODED_BASE64 = 22;

    public const TAG_ENCODED_BASE16 = 23;

    public const TAG_ENCODED_CBOR = 24;

    public const TAG_STRING_REFERENCE = 25;

    public const TAG_PERL_OBJECT = 26;

    public const TAG_LANGUAGE_INDEPENDENT_OBJECT = 27;

    public const TAG_SHAREABLE = 28;

    public const TAG_SHARED_REFERENCE = 29;

    public const TAG_RATIONAL_NUMBER = 30;

    public const TAG_URI = 32;

    public const TAG_BASE64_URL = 33;

    public const TAG_BASE64 = 34;

    public const TAG_REGULAR_EXPRESSION = 35;

    public const TAG_MIME = 36;

    public const TAG_UUID = 37;

    public const TAG_LANGUAGE_TAGGED_STRING = 38;

    public const TAG_IDENTIFIER = 39;

    public const TAG_ROW_MAJOR_MULTI_DIMENSIONAL_ARRAY = 40;

    public const TAG_HOMOGENEOUS_ARRAY = 41;

    public const TAG_IPLD_CONTENT_IDENTIFIER = 42;

    public const TAG_IPV4 = 52;

    public const TAG_IPV6 = 54;

    public const TAG_CWT = 61;

    public const TAG_ENCODED_CBOR_SEQUENCE = 63;

    public const TAG_TYPED_ARRAY_UINT8 = 64;

    public const TAG_TYPED_ARRAY_UINT16_BE = 65;

    public const TAG_TYPED_ARRAY_UINT32_BE = 66;

    public const TAG_TYPED_ARRAY_UINT64_BE = 67;

    public const TAG_TYPED_ARRAY_UINT8_CLAMPED = 68;

    public const TAG_TYPED_ARRAY_UINT16_LE = 69;

    public const TAG_TYPED_ARRAY_UINT32_LE = 70;

    public const TAG_TYPED_ARRAY_UINT64_LE = 71;

    public const TAG_TYPED_ARRAY_SINT8 = 72;

    public const TAG_TYPED_ARRAY_SINT16_BE = 73;

    public const TAG_TYPED_ARRAY_SINT32_BE = 74;

    public const TAG_TYPED_ARRAY_SINT64_BE = 75;

    public const TAG_TYPED_ARRAY_SINT16_LE = 77;

    public const TAG_TYPED_ARRAY_SINT32_LE = 78;

    public const TAG_TYPED_ARRAY_SINT64_LE = 79;

    public const TAG_TYPED_ARRAY_FLOAT16_BE = 80;

    public const TAG_TYPED_ARRAY_FLOAT32_BE = 81;

    public const TAG_TYPED_ARRAY_FLOAT64_BE = 82;

    public const TAG_TYPED_ARRAY_FLOAT128_BE = 83;

    public const TAG_TYPED_ARRAY_FLOAT16_LE = 84;

    public const TAG_TYPED_ARRAY_FLOAT32_LE = 85;

    public const TAG_TYPED_ARRAY_FLOAT64_LE = 86;

    public const TAG_TYPED_ARRAY_FLOAT128_LE = 87;

    public const TAG_COSE_ENCRYPT = 96;

    public const TAG_COSE_MAC = 97;

    public const TAG_COSE_SIGN = 98;

    public const TAG_DATE = 100;

    public const TAG_STRING_REFERENCE_NAMESPACE = 256;

    public const TAG_BINARY_MIME = 257;

    public const TAG_SET = 258;

    public const TAG_EXPLICIT_MAP = 259;

    public const TAG_NETWORK_ADDRESS = 260;

    public const TAG_NETWORK_ADDRESS_PREFIX = 261;

    public const TAG_EXTENDED_TIME = 1001;

    public const TAG_DURATION = 1002;

    public const TAG_PERIOD = 1003;

    public const TAG_DATE_STRING = 1004;

    public const TAG_COLUMN_MAJOR_MULTI_DIMENSIONAL_ARRAY = 1040;

    public const TAG_CBOR = 55799;

    public function getMajorType(): int;

    public function getAdditionalInformation(): int;
}
