import CustomBonusSystemApiService from "../services/custom-bonus-system.api.service";

Shopware.Service().register('customBonusSystemApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new CustomBonusSystemApiService(initContainer.httpClient, container.loginService);
});