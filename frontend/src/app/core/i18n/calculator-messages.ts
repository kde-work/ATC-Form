/** Ключи и тексты UI калькулятора. */
export type AppLocale = 'zh' | 'en';

export const DEFAULT_LOCALE: AppLocale = 'zh';

export const LOCALE_STORAGE_KEY = 'atc_ui_locale';

export type CalculatorMessages = {
  siteTitle: string;
  pageHeading: string;
  pageLead: string;
  platform: string;
  deliveryChannel: string;
  selectPlatform: string;
  loadingChannels: string;
  selectPlatformFirst: string;
  noActiveChannels: string;
  selectChannel: string;
  channelHint: string;
  physicalWeight: string;
  hideDimensions: string;
  showDimensions: string;
  dimensions: string;
  length: string;
  width: string;
  height: string;
  orderCost: string;
  optional: string;
  currency: string;
  currencyAria: string;
  calculate: string;
  calculating: string;
  emptyHint: string;
  resultHeading: string;
  eligible: string;
  notEligible: string;
  notEligibleMessage: string;
  volumetricWeight: string;
  chargeableWeight: string;
  fixedFeeCny: string;
  ratePerGramCny: string;
  finalCostCny: string;
  orderCostCny: string;
  billedWeight: string;
  fixedFeeRub: string;
  ratePerKgRub: string;
  finalCostRub: string;
  exchangeRate: string;
  warnings: string;
  notes: string;
  language: string;
  fieldRequired: string;
  fieldNumberFormat: string;
  fieldPositive: string;
  fieldInvalid: string;
  fixHighlightedFields: string;
  unexpectedError: string;
  networkError: string;
};

export const CALCULATOR_MESSAGES: Record<AppLocale, CalculatorMessages> = {
  zh: {
    siteTitle: '运费计算器',
    pageHeading: '计算运费',
    pageLead: '输入包裹信息。价格由服务器计算；本表单仅做输入内容校验。',
    platform: '电商平台',
    deliveryChannel: '配送渠道',
    selectPlatform: '选择平台',
    loadingChannels: '正在加载渠道…',
    selectPlatformFirst: '请先选择平台',
    noActiveChannels: '暂无可用渠道',
    selectChannel: '选择渠道',
    channelHint: '渠道随表单预加载；选择平台后自动过滤。',
    physicalWeight: '实际重量，克',
    hideDimensions: '隐藏尺寸',
    showDimensions: '显示尺寸（可选）',
    dimensions: '外包装尺寸，厘米',
    length: '长',
    width: '宽',
    height: '高',
    orderCost: '订单货值',
    optional: '（可选）',
    currency: '货币',
    currencyAria: '订单货值货币',
    calculate: '开始计算',
    calculating: '计算中…',
    emptyHint: '提交表单后即可查看运费明细。',
    resultHeading: '计算结果',
    eligible: '符合要求',
    notEligible: '不符合渠道要求',
    notEligibleMessage: '该包裹无法使用所选配送渠道',
    volumetricWeight: '体积重量，克',
    chargeableWeight: '计费重量，克',
    fixedFeeCny: '固定费用，人民币',
    ratePerGramCny: '每克单价，人民币',
    finalCostCny: '最终运费，人民币',
    orderCostCny: '订单货值（折合人民币）',
    billedWeight: '计费重量，克',
    fixedFeeRub: '固定费用，卢布',
    ratePerKgRub: '每公斤单价，卢布',
    finalCostRub: '最终运费，卢布',
    exchangeRate: '当前卢布→人民币汇率',
    warnings: '提示',
    notes: '备注',
    language: '语言',
    fieldRequired: '此字段为必填项。',
    fieldNumberFormat: '请输入有效的小数（例如 12.5）。',
    fieldPositive: '数值必须大于 0。',
    fieldInvalid: '无效的值。',
    fixHighlightedFields: '请先修正标出的字段，再开始计算。',
    unexpectedError: '发生意外错误。',
    networkError: '网络或服务器错误。',
  },
  en: {
    siteTitle: 'Delivery cost calculator',
    pageHeading: 'Calculate delivery cost',
    pageLead:
      'Enter shipment details. Pricing is calculated by the server; this form only validates inputs.',
    platform: 'Platform',
    deliveryChannel: 'Delivery channel',
    selectPlatform: 'Select platform',
    loadingChannels: 'Loading channels…',
    selectPlatformFirst: 'Select platform first',
    noActiveChannels: 'No active channels',
    selectChannel: 'Select channel',
    channelHint: 'Channels are preloaded with the form; filtered after platform is selected.',
    physicalWeight: 'Physical weight, g',
    hideDimensions: 'Hide dimensions',
    showDimensions: 'Show dimensions (optional)',
    dimensions: 'Dimensions, cm',
    length: 'Length',
    width: 'Width',
    height: 'Height',
    orderCost: 'Order cost',
    optional: '(optional)',
    currency: 'Currency',
    currencyAria: 'Order cost currency',
    calculate: 'Calculate',
    calculating: 'Calculating…',
    emptyHint: 'Submit the form to see the delivery cost breakdown.',
    resultHeading: 'Calculation result',
    eligible: 'Eligible',
    notEligible: 'Not eligible',
    notEligibleMessage: 'This shipment is not eligible for the selected delivery channel.',
    volumetricWeight: 'Volumetric weight, g',
    chargeableWeight: 'Chargeable weight, g',
    fixedFeeCny: 'Fixed fee, CNY',
    ratePerGramCny: 'Rate per gram, CNY',
    finalCostCny: 'Final cost, CNY',
    orderCostCny: 'Order cost, CNY',
    billedWeight: 'Billed weight, g',
    fixedFeeRub: 'Fixed fee, RUB',
    ratePerKgRub: 'Rate per kg, RUB',
    finalCostRub: 'Final cost, RUB',
    exchangeRate: 'Active RUB → CNY rate',
    warnings: 'Warnings',
    notes: 'Notes',
    language: 'Language',
    fieldRequired: 'This field is required.',
    fieldNumberFormat: 'Enter a valid decimal number (e.g. 12.5).',
    fieldPositive: 'Value must be greater than 0.',
    fieldInvalid: 'Invalid value.',
    fixHighlightedFields: 'Please fix the highlighted fields before calculating.',
    unexpectedError: 'Unexpected error.',
    networkError: 'Network or server error.',
  },
};
