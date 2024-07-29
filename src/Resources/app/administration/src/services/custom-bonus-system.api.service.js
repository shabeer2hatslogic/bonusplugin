const ApiService = Shopware.Classes.ApiService;

export default class CustomBonusSystemApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'custom') {
        super(httpClient, loginService, apiEndpoint);
    }

    getSumPointsSalesChannels(filter = 'all', limit = 5, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/get-sum-points-sales-channels`, { filter, limit }, { headers })
            .then(response => response.data);
    }

    getTopEarnedPointsCustomers(limit = 5, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/get-top-earned-points-customers`, { limit }, { headers })
            .then(response => response.data);
    }

    getTopCreditPointsCustomers(limit = 5, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/get-top-credit-points-customers`, { limit }, { headers })
            .then(response => response.data);
    }
}