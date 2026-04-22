<?php
/**
 * This file has implementation for csrfpAction class.
 */

if (!defined('__CSRF_PROTECTOR_ACTION__')) {
    // to avoid multiple declaration errors
    define('__CSRF_PROTECTOR_ACTION__', true);

    /**
     * Enumerator for actions.
     */
    abstract class csrfpAction {
        /**
         * Variable: ForbiddenResponseAction
         * Action of sending back 403 response code
         * @var int
         */
        const ForbiddenResponseAction = 0;

        /**
         * Variable: ClearParametersAction
         * Action of clearning all request parameters
         * @var int
         */
        const ClearParametersAction = 1;

        /**
         * Variable: RedirectAction
         * Action of redirecting users to another location
         * @var int
         */
        const RedirectAction = 2;

        /**
         * Variable: CustomErrorMessageAction
         * Action of sending back a custom message
         * @var int
         */
        const CustomErrorMessageAction = 3;

        /**
         * Variable: InternalServerErrorResponseAction
         * Action of sending back 5XX response code
         * @var int
         */
        const InternalServerErrorResponseAction = 4;
    }
}