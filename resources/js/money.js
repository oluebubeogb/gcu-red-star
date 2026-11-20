// Paystack amounts are kobo (₦1 = 100 kobo).
export const naira = (kobo) => '₦' + (Number(kobo || 0) / 100).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
