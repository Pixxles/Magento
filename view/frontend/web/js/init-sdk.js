define([], function () {
    return function (config) {
        Kount.setup({
            clientID:   config.merchant_id.toString(),
            sessionID:  config.sessionId,
            environment: config.environment,
            collectBrowserData: true,
            isSinglePageApp: false,
            isDebugEnabled: config.environment === 'TEST',
        });
    };
});