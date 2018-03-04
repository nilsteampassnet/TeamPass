<?php

class AXSErrorcodes {
	
    // Global Integer Return Values used in all methods that return Integers
    // and are called from the outside world (webservices, etc.)
    // List of returnvalue ranges:
    // x	> 0			: Positive Result (true with more infos (depending on application)
    // x	== 0		: Default FALSE
    // x	< 0			: Negative Result (normally false, but with more infos)
    // x	== -100		: No Active Message found
    // x	== -1000	: Internal Server Error, see server log for details (should not happen)
    // x	< -1000		: ERROR values
	
    /**
     * Extract the error code contained in an ERROR string and return the code
     * @param message ERROR String received from AXS web service
     * @return error code found in ERROR message or -10000 if no error code could be found
     */
    function getErrorcode($message) {
        $errorcode = -10000;
        if (($begin = strpos($message, "ERROR (")) === FALSE) {
                    $begin = -1;
        }
        if (($end = strpos($message, ")")) === FALSE) {
                    $end = -1;
        }
		
        if ($begin >= 0 && $end >= 0) {
            $realBegin = $begin + 7;
            $errorcode = substr($message, $realBegin, $end - $realBegin);
        }
        return $errorcode;
    }

    /*
	 * Reserved errors
	 */
    const RETURN_VALUE_DEFAULT_FALSE = 0;

    const ERROR_HEDGEID_WRONG = -1;
    const ERROR_RESPONSE_NOT_VALID_ANYMORE = -2;
    const ERROR_RESPONSE_VERFICATION_FAILED_MORE_TRY_LEFT = -3;
    const ERROR_RESPONSE_MAX_TRIES_REACHED = -4;
	
    const ERROR_RESPONSE_WITHOUT_MESSAGE = -100;
    const ERROR_RESPONSE_NULL = -101;
    const ERROR_HEDGEID_NULL = -102;
    const ERROR_RESPONSE_EMPTY = -103;
    const ERROR_HEDGEID_EMPTY = -104;
	
    /*
	 * Token errors (-1001 to -1099)
	 */
    const ERROR_TOKEN_NOT_FOUND = -1001;
    const ERROR_TOKEN_LOCKED = -1002;
    const ERROR_TOKEN_DOES_NOT_SUPPORT_APPLICATION = -1003;
    const ERROR_TOKEN_EXPORT = -1004;
    const ERROR_TOKEN_REMOVE = -1005;
    const ERROR_TOKEN_READY_FOR_PRODUCTIONLOT_NOT_FOUND = -1006;
    const ERROR_TOKEN_NOT_IN_PRODUCTION_STATE = -1007;
    const ERROR_TOKEN_TYPE_NOT_FOUND = -1008;
    const ERROR_TOKEN_WRONG_STATE = -1009;
    const ERROR_TOKEN_BRLC_NOT_FOUND = -1010;
    const ERROR_TOKEN_ASSIGN_SAME_SERIAL_NUMBER_TO_OTHER_TOKEN = -1011;
    const ERROR_TOKEN_SERIAL_NUMBER_ALREADY_ASSIGNED = -1012;
    const ERROR_TOKEN_UNLOCK_COUNTER_MAXIMUM_REACHED = -1013;
    const ERROR_TOKEN_REVOKED = -1014;
    const ERROR_TOKEN_DOES_NOT_SUPPORT_BRANDING = -1015;
    const ERROR_TOKEN_TO_DELETE_IN_WRONG_STATE = -1016;
	
    const ERROR_DR_TOKENENTITY_NOT_FOUND = -1025;
	
    // ERROR if the token could not be found on the local server
    // (automatic key fetching not enabled)
    const ERROR_TOKEN_NOT_FOUND_NO_KEYFETCHING = -1051;
    const ERROR_TOKEN_NOT_FOUND_KEYFETCHING_TEMPORARY_NOT_AVAILABLE = -1052;
	
    /*
	 * Key errors (-1100 to -1199)
	 */
    const ERROR_KEY_NOT_FOUND = -1100;
    const ERROR_NO_UNASSIGNED_KEY_FOUND = -1101;
    const ERROR_ISSUER_KEY_NOT_FOUND = -1102;
    const ERROR_KEY_NOT_UPDATED = -1103;
    /* removed 2008-11-13 - smm	
	 * The error does not occur anymore
	 * const ERROR_KEY_FOR_KEYLOADING_NOT_CREATED = -1104;
	 */
    const ERROR_REEXPORT_KEYS_ON_NORMAL_AXS_AS = -1105;
    const ERROR_NUMBER_OF_KEYS_OUT_OF_BOUNDS = -1106;
    const ERROR_COULD_NOT_FIND_NUMBER_OF_KEYS = -1107;
    const ERROR_CANNOT_ASSIGN_AXS_EXCLUSIVE_KEY_TO_AA = -1108;
    const ERROR_TEMPORARY_KEY_NOT_CALCULATED = -1109;
    const ERROR_SM_NOT_AUTHORIZED_TO_RECEIVE_FMS_KEY = -1110;
    const ERROR_NOT_AUTHORIZED_TO_DELETE_KEY = -1111;
    const ERROR_CANNOT_FORCE_KEY_DELETION = -1112;
    // the key is locked, introduced in V2.20.00
    const ERROR_KEY_LOCKED = -1113;
    // non-expiring key errors, introduced in V2.20.00
    const ERROR_NON_EXPIRING_KEY_NOT_FOUND = -1114;
    const ERROR_NON_EXPIRING_KEY_NOT_READY_TWO_ADDITIONAL_AUTHENTICATIONS_NEEDED = -1115;
    const ERROR_NON_EXPIRING_KEY_NOT_READY_ONE_ADDITIONAL_AUTHENTICATION_NEEDED = -1116;
	
    // ERROR if no free key is available on the local server and
    // the automatic key fetching is not enabled
    const ERROR_NO_UNASSIGNED_KEY_FOUND_NO_KEYFETCHING = -1151;
    const ERROR_KEY_NOT_FOUND_KEYFETCHING_TEMPORARY_NOT_AVAILABLE = -1152;
	
	
    /*
	 * Firmware errors (-1200 to -1299)
	 */
    const ERROR_FW_NOT_FOUND = -1200;
    const ERROR_FW_SUCCESSOR_NOT_FOUND = -1201;
    const ERROR_FW_INITIAL_KEY_NOT_FOUND = -1202;
    const ERROR_FW_INITIAL_KEY_NOT_HEX = -1203;
    const ERROR_FW_INITIAL_KEY_INCORRECT_LENGTH = -1204;
    const ERROR_FW_ACTUAL_VERSION_NULL = -1205;
    const ERROR_FW_ACTUAL_VERSION_EMPTY = -1206;
    const ERROR_FW_VERSION_NOT_VALID = -1207;
    const ERROR_FW_ENCRYPTED_FIRMWARE_URL_NOT_SET = -1208;
    const ERROR_FW_ANCESTOR_ALREADY_HAS_SUCCESSOR = -1209;
    const ERROR_FW_UPDATE_SEED_NOT_SET = -1210;

    /*
	 * AA errors (-1300 to -1399)
	 */
    const ERROR_AA_NOT_FOUND = -1300;
    const ERROR_AA_NOT_TOKEN_ISSUER = -1301;
    const ERROR_AA_DOES_NOT_OWN_BRANDING = -1302;
    const ERROR_AA_NO_ENROLMENT_RIGHT = -1303;
    const ERROR_AA_DOES_NOT_MEET_MIN_ENROLMENT_TYPE = -1304;
    const ERROR_AA_SIGNATURE_NOT_VERIFIED = -1305;
    const ERROR_AA_SIGNATURE_NULL_OR_EMPTY = -1306;
    const ERROR_AA_SIGNATURE_DATA_NOT_SET_OR_EMPTY = -1307;
    const ERROR_AA_BRANDINGID_NULL = -1308;
    const ERROR_AA_BRANDINGID_EMPTY = -1309;
    const ERROR_AA_BRANDINGID_TOO_SHORT = -1310;
    const ERROR_AA_BRANDINGID_TOO_LONG = -1311;
    const ERROR_AA_BRANDINGID_NOT_NUMBER = -1312;
    const ERROR_AA_NAME_NULL = -1313;
    const ERROR_AA_NAME_EMPTY = -1314;
    const ERROR_AA_NAME_TOO_SHORT = -1315;
    const ERROR_AA_NAME_TOO_LONG = -1316;
    const ERROR_AA_BRANDINGID_NOT_HEX = -1317;
    const ERROR_IL_NOT_AUTHORIZED_TO_IMPORT_BLC = -1318;
		
    /*
	 * Branding errors (-1400 to -1499)
	 */
    const ERROR_BRANDING_NOT_FOUND = -1400;
    const ERROR_BRANDING_EMPTY = -1401;
    const ERROR_BRANDING_INCOMPLETE = -1402;
    const ERROR_BRANDING_ALREADY_EXIST = -1403;
    const ERROR_BRANDING_LOADING_NOT_CONFIRMED = -1404;
    const ERROR_BRANDING_SIGNATURE_NOT_VERIFIED = -1405;
    const ERROR_BRANDING_SIGNATURE_NOT_HEX = -1406;
    const ERROR_BRANDING_SIGNATURE_NOT_CALCULATED = -1407;
    const ERROR_BRANDINGID_NULL = -1408;
    const ERROR_BRANDINGID_EMPTY = -1409;
    const ERROR_BRANDINGID_TOO_SHORT = -1410;
    const ERROR_BRANDINGID_TOO_LONG = -1411;
    const ERROR_BRANDINGID_NOT_NUMBER = -1412;
    const ERROR_BRANDING_NAME_NULL = -1413;
    const ERROR_BRANDING_NAME_EMPTY = -1414;
    const ERROR_BRANDING_NAME_TOO_SHORT = -1415;
    const ERROR_BRANDING_NAME_TOO_LONG = -1416;
    const ERROR_BRANDING_TYPE_UNKNOWN = -1417;
    const ERROR_POWER_BRANDING_NOT_FOUND = -1418;
    // error for new branding (version 2) - 10.03.09 crohr
    const ERROR_KBIN_NOT_SET = -1430;
    const ERROR_KBIN_LENGTH_NOT_CORRECT = -1431;
    const ERROR_BRDIG_NOT_VERIFIED = -1432;
    const ERROR_BISC_NOT_VERIFIED = -1433;
    const ERROR_CSS_NOT_VERIFIED = -1434;
    const ERROR_ILN_NOT_VERIFIED = -1435;
    const ERROR_CSS_INCOMPLETE = -1436;
    const ERROR_CSS_EMPTY = -1437;
    const ERROR_CSS_SIGNATURE_NOT_VERIFIED = -1438;
    const ERROR_BRDIG_SIGNATURE_NOT_VERIFIED = -1439;
    const ERROR_BRDIG_SIGNATURE_NOT_HEX = -1440;
    const ERROR_BRANDINGID_NOT_HEX = -1441;
    const ERROR_OID_LENGTH_NOT_CORRECT = -1442;
    // the CSS was created for another system (production, enterprise, evaluation, ...)
    const ERROR_CSS_BELONGS_TO_OTHER_SYSTEM = -1443;
    // the systemtype in the kbin is not the same as the one in the CSS
    const ERROR_KBIN_FOR_OTHER_SYSTEM_THAN_CSS = -1444;
	
    /*
	 * Property errors (-1600 to -1699)
	 */
    const ERROR_PROPERTY_NOT_NUMBER = -1600;
    const ERROR_PROPERTY_NOT_SET = -1601;
    const ERROR_PROPERTY_VALUE_NOT_VALID = -1602;
	
    /*
	 * platform errors (-1700 to -1799)
	 */
    const ERROR_PLATFORM_OPERATOR_NOT_SET = -1700;
    const ERROR_PLATFORMID_NULL = -1701;
    const ERROR_PLATFORMID_EMPTY = -1702;
    const ERROR_PLATFORMID_NOT_NUMBER = -1703;
    const ERROR_PLATFORMID_TOO_SHORT = -1704;
    const ERROR_PLATFORMID_TOO_LONG = -1705;
    const ERROR_PLATFORM_NAME_NULL = -1706;
    const ERROR_PLATFORM_NAME_EMPTY = -1707;
    const ERROR_PLATFORM_NAME_TOO_SHORT = -1708;
    const ERROR_PLATFORM_NAME_TOO_LONG = -1709;
    const ERROR_PLATFORMID_LENGTH_NOT_CORRECT = -1710;
    const ERROR_PLATFORMID_NOT_HEX = -1711;
	
    /*
	 * XML errors (-1800 to -1899)
	 */
    const ERROR_XML_DATA_NOT_EXPORTED = -1800;
    const ERROR_XML_DATA_NOT_PARSED = -1801;
    const ERROR_XML_DATA_NOT_IMPORTED = -1802;
    const ERROR_XML_DATA_NULL = -1803;
    const ERROR_XML_DATA_EMPTY = -1804;

    /*
	 * TCS errors (-1900 to -1999)
	 */
    const ERROR_TCS_FILE_NOT_ENCRYPTED = -1900;
    const ERROR_TCS_FILE_SIGNATURE_NOT_CREATED = -1901;
    const ERROR_TCS_FILE_NOT_READABLE = -1902;
    const ERROR_TCS_FILE_STRUCTURE_NOT_VALID = -1903;
    const ERROR_TCS_SIGNATURE_NOT_VERIFIED = -1904;
    const ERROR_TCS_SIGNATURE_NOT_VERIFIED_DATA_NOT_VALID = -1905;
    const ERROR_TCS_SIGNATURE_NOT_VERIFIED_CERTIFICATE_NOT_USABLE = -1906;
    const ERROR_TCS_SIGNATURE_NOT_VERIFIED_SIGNATURE_ALGORITHM_NOT_AVAILABLE = -1907;
    const ERROR_TCS_DATA_ENCRYPTION_ALGORITHM_NOT_AVAILABLE = -1908;
    const ERROR_TCS_DATA_NOT_IMPORTED = -1909;
    const ERROR_TCS_DATA_PRIVATE_KEY_INVALID = -1910;
	
    /*
	 * Production lot errors (-2000 to -2099)
	 */
    const ERROR_PRODUCTION_LOT_NOT_FOUND = -2000;
    const ERROR_LOT_NUMBER_NEGATIVE = -2001;
    const ERROR_LOT_NUMBER_ALREADY_EXISTS = -2002;
			
    /*
	 *  Keystore and TPS errors (-2100 to -2199)
	 */
    const ERROR_KEYSTORE_NOT_FOUND = -2100;
    const ERROR_KEYSTORE_LOCATION_NOT_SET_OR_EMPTY = -2101;
    const ERROR_KEYSTORE_PASSWORD_NOT_SET_OR_EMPTY = -2102;
    const ERROR_KEYSTORE_NOT_LOADED = -2103;
    const ERROR_KEYSTORE_KEY_NOT_LOADED = -2104;
    const ERROR_AXS_CERTIFICATE_NOT_FOUND = -2105;
    const ERROR_COULD_NOT_GET_CERTIFICATE = -2106;
    const ERROR_COULD_NOT_GET_PUBLIC_KEY = -2107;
    const ERROR_TPS_KEY_NOT_LOADED_FROM_KEYSTORE = -2108;
    const ERROR_TPS_CERTIFICATE_NOT_LOADED_FROM_KEYSTORE = -2109;
    const ERROR_TPS_SERVER_CONNECTION = -2110;
    const ERROR_TPS_KEY_NOT_EXPORTED = -2111;
    const ERROR_TPS_OTBLC_NOT_CREATED = -2112;

    /*
	 * Data errors (-2200 to -2399)
	 */
	
    // Codebook errors
    const ERROR_CODEBOOK_ENTRY_NOT_FOUND = -2200;
    const ERROR_CODEBOOK_NUMBER_OF_ENTRIES_DO_NOT_MATCH = -2201;
    const ERROR_CODEBOOK_ENTRY_LENGTH_NOT_VALID = -2202;
		
    // FingerCode errors
    const ERROR_FINGERCODE_NULL = -2210;
    const ERROR_FINGERCODE_TOO_SHORT = -2211;
    const ERROR_FINGERCODE_TOO_LONG = -2212;
    const ERROR_FINGERCODE_INVALID_SYMBOLS = -2213;
    const ERROR_FINGERCODE_CHAR_REPETITION = -2214;
    const ERROR_FINGERCODE_VIOLATES_DOMAIN = -2215;
	
    // PIN errors
    const ERROR_PIN_LENGTH_OUT_OF_BOUNDS = -2220;
    const ERROR_PIN_LENGTH_FINGERCODE_NOT_SET = -2221;
	
    // Text payload errors
    const ERROR_TEXT_PAYLOAD_NULL = -2230;
    const ERROR_TEXT_PAYLOAD_EMPTY = -2231;
    const ERROR_TEXT_PAYLOAD_TOO_LONG = -2232;
    const ERROR_TEXT_CONTAINS_NON_DISPLAYABLE_CHARACTER = -2233;
    const ERROR_TEXT_TOO_MANY_LINES = -2234;
	
    // AclRequested errors
    const ERROR_ACL_REQUESTED_NULL = -2250;
    const ERROR_ACL_REQUESTED_EMPTY = -2251;
    const ERROR_ACL_REQUESTED_TOO_SHORT = -2252;
    const ERROR_ACL_REQUESTED_TOO_LONG = -2253;
    const ERROR_ACL_REQUESTED_NOT_HEX = -2254;
	
    // Description errors
    const ERROR_DESCRIPTION_TOO_LONG = -2270;
	
    // Data errors
    const ERROR_DATA_NULL = -2280;
    const ERROR_DATA_EMPTY = -2281;
    const ERROR_DATA_TOO_LONG = -2282;
    const ERROR_DATA_NOT_HEX = -2283;
	
    // AuthenticationLevel error
    const ERROR_AUTHENTICATION_LEVEL_OUT_OF_BOUNDS = -2290;
	
    // ReturnPath error
    const ERROR_RETURN_PATH_OUT_OF_BOUNDS = -2300;
	
    // min_aut_lev error
    const ERROR_MIN_AUT_LEV_OUT_OF_BOUNDS = -2310;
	
    // HexData errors
    const ERROR_HEX_DATA_NULL = -2320;
    const ERROR_HEX_DATA_NOT_HEX = -2321;
		
    // BRAK errors
    const ERROR_BRAK_NOT_SET = -2330;
    const ERROR_BRAK_LENGTH_NOT_CORRECT = -2331;
    const ERROR_BARS_LENGTH_NOT_CORRECT = -2332;

    // BRAC errors
    const ERROR_BRAC_NULL = -2340;
    const ERROR_BRAC_EMPTY = -2341;
    const ERROR_BRAC_NOT_HEX = -2342;
	
    // OTBLC errors
    const ERROR_OTBLC_NOT_FOUND = -2350;
    const ERROR_OTBLC_NULL = -2351;
    const ERROR_OTBLC_EMPTY = -2352;
    const ERROR_OTBLC_TOO_SHORT = -2353;
    const ERROR_OTBLC_TOO_LONG = -2354;
    const ERROR_OTBLC_NOT_HEX = -2355;
    const ERROR_OTBLC_DIFFERENT = -2356;
	
    // BLC nonce errors
    const ERROR_BLC_NONCE_FORMAT_NOT_CORRECT = -2360;
    const ERROR_BLC_NONCE_NULL_OR_EMPTY = -2361;
	
    // enrolment type error
    const ERROR_ENROLMENT_TYPE_UNKNOWN = -2370;
	
    // lock errors
    const ERROR_LOCKING_REASON_NULL = -2380;
    const ERROR_LOCKING_REASON_EMPTY = -2381;
    const ERROR_UNLOCKING_REASON_NULL = -2382;
    const ERROR_UNLOCKING_REASON_EMPTY = -2383;	
	
    // idT error
    const ERROR_IDT_INVALID = -2390;
	
    /*
	 * User errors (-2400 to -2479)
	 */
    const ERROR_USER_NOT_FOUND = -2400;
    const ERROR_GROUP_NOT_FOUND = -2401;
    const ERROR_USERNAME_AND_AAID_CANNOT_BE_THE_SAME = -2402;
    const ERROR_USER_ALREADY_CREATED = -2403;
    const ERROR_GROUP_ALREADY_EXIST = -2404;
    const ERROR_USER_ALREADY_ASSIGNED_TO_AAID = -2405;
    const ERROR_USER_ALREADY_ASSIGNED_TO_GROUP = -2406;
    const ERROR_USER_ALREADY_REMOVED_FROM_GROUP = -2407;
    const ERROR_USER_CAN_NOT_BE_DELETED = -2408;
    const ERROR_USERNAME_NOT_CORRECT = -2409; 
    const ERROR_USERNAME_AAID_NOT_CORRECT = -2410;
    const ERROR_USERNAME_GROUPNAME_NOT_CORRECT = -2411;
    const ERROR_GROUPNAME_NOT_CORRECT = -2412;
    const ERROR_PASSWORD_NOT_CORRECT = -2413;
    const ERROR_USERNAME_PASSWORD_NOT_SET = -2414;
    const ERROR_GROUPS_EMPTY = -2415;
    const ERROR_DEFAULT_ADMIN_USER_NOT_FOUND = -2416;
    const ERROR_DEFAULT_ADMIN_USER_NOT_VALID = -2417;
    const ERROR_DEFAULT_ADMIN_USER_CAN_NOT_BE_DELETED = -2418;
    const ERROR_DEFAULT_ADMIN_USER_CANNOT_BE_REMOVED_FROM_GROUP = -2419;
    const ERROR_DEFAULT_ADMIN_USER_ALREADY_CREATED = -2420;
    const ERROR_PASSWORD_OF_DEFAULT_ADMIN_USER_NOT_CHANGED = -2421;
    const ERROR_PERMISSION_DENIED = -2422;

    /*
	 * Configuration errors (-2480 to -2499)
	 */
    const ERROR_CONFIGURATION_NOT_FOUND = -2480;
    const ERROR_CONFIGURATION_ITEM_NOT_FOUND = -2481;
    const ERROR_CONFIGURATION_NAME_TOO_SHORT = -2482;
    const ERROR_CONFIGURATION_NAME_TOO_LONG = -2483;
    const ERROR_CONFIGURATION_DESCRIPTION_TOO_LONG = -2484;
    const ERROR_CONFIGURATION_TYPE_OUT_OF_BOUNDS = -2485;
    const ERROR_CONFIGURATION_POSITION_OUT_OF_BOUNDS = -2486;
    const ERROR_CONFIGURATION_ALREADY_EXIST = -2487;
    const ERROR_CONFIGURATION_ITEM_NAME_TOO_LONG = -2488;
    const ERROR_CONFIGURATION_ITEM_ALREADY_EXIST = -2489;
	
    /*
	 * Generator errors (-2500 to -2599)
	 */
    const ERROR_BAC_GENERATOR_NOT_INITIALIZED = -2500;
    const ERROR_FLICKERING_GENERATOR_NOT_FOUND = -2501;
    const ERROR_MESSAGE_GENERATOR_NOT_FOUND = -2502;
    const ERROR_CODEBOOK_GENERATOR_NOT_INITIALIZED = -2503;
    const ERROR_DEBUG_MESSAGE_GENERATOR_NOT_FOUND = -2504;
    const ERROR_TCS_GENERATOR_CLASS_NOT_VALID = -2505;
    const ERROR_TCS_GENERATOR_NOT_CREATED = -2506;
	
    /*
	 * Other errors (-2600 to -xxxx)
	 */
    const ERROR_DOS_TIMELIMIT = -2600;
    const ERROR_IO = -2601;
    const ERROR_WSDL_LOCATION_PROPERTY_NOT_SET = -2602;
    const ERROR_AXS_UPDATER_SERVICE_NOT_CALLED = -2603;
    const ERROR_DOMAIN_VIOLATION_BY_UPDATE_SESSION_SEED = -2604;
    const ERROR_CREATE_CORE_WITH_EXISTING_USCN = -2605;
	
    /*
	 * Roaming errors (-2700 to -2799)
	 */
    // configuration problems
    const ERROR_KEYFETCHING_NOT_CONFIGURED = -2700;
    // connection problems sm - cm
    const ERROR_KEYFETCHING_SM_CANNOT_CONNECT_TO_CM_WSDL_WRONG = -2710;
    const ERROR_KEYFETCHING_SM_CANNOT_CONNECT_TO_CM_NOT_AUTHORIZED = -2711;
	
    // errors outside of this server
    const ERROR_KEYFETCHING_PERMANENT_ERROR_OUTSIDE_OF_THIS_SERVER = -2720;
    const ERROR_KEYFETCHING_TEMPORARY_ERROR_OUTSIDE_OF_THIS_SERVER = -2721;
	
    // timeouts
    const ERROR_KEYFETCHING_TIMEOUT_ON_SERVER = -2730;
	
    const ERROR_KEYSTORE_LOCATION_NOT_CORRECT = -2740;
		
    /* removed 2009-02-18 - crohr	
	 * The error does not occur anymore
	 * const ERROR_SERVER_TIMEOUT = -3000;
	 */	
    const ERROR_WEBSERVICE = -3001;
    const ERROR_DATABASE_CORRUPTED = -3002;
    const ERROR_WSDL_URL_NOT_ACCESSIBLE = -3003;
    const ERROR_PARAMETER_NULL = -4000;
    const ERROR_PARAMETER_EMPTY = -4001;
    const ERROR_PARAMETER_TOO_LONG = -4002;
	
    const ERROR_METHOD_NOT_SUPPORTED_ANYMORE = -5000;
	
    const ERROR_FORWARD_SECURITY_NEWS_NOT_FOUND = -5100;
    const ERROR_NEWS_NOT_SUPPORTED = -5101;
		
    const ERROR_UNDEFINED = -9999;
}
?>