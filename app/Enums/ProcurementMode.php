<?php

namespace App\Enums;

/** Procurement modes recognized by RA 12009 Article IV (docs/08). */
enum ProcurementMode: string
{
    case CompetitiveBidding = 'competitive_bidding';
    case LimitedSourceBidding = 'limited_source_bidding';
    case CompetitiveDialogue = 'competitive_dialogue';
    case UnsolicitedOfferWithBidMatching = 'unsolicited_offer_with_bid_matching';
    case DirectContracting = 'direct_contracting';
    case DirectAcquisition = 'direct_acquisition';
    case RepeatOrder = 'repeat_order';
    case SmallValueProcurement = 'small_value_procurement';
    case NegotiatedProcurement = 'negotiated_procurement';
    case DirectSales = 'direct_sales';
    case DirectProcurementForSti = 'direct_procurement_for_sti';

    public function label(): string
    {
        return match ($this) {
            self::CompetitiveBidding => 'Competitive bidding',
            self::LimitedSourceBidding => 'Limited source bidding',
            self::CompetitiveDialogue => 'Competitive dialogue',
            self::UnsolicitedOfferWithBidMatching => 'Unsolicited Offer with Bid Matching',
            self::DirectContracting => 'Direct contracting',
            self::DirectAcquisition => 'Direct acquisition',
            self::RepeatOrder => 'Repeat order',
            self::SmallValueProcurement => 'Small value procurement',
            self::NegotiatedProcurement => 'Negotiated procurement',
            self::DirectSales => 'Direct sales',
            self::DirectProcurementForSti => 'Direct Procurement for Science, Technology and Innovation',
        };
    }
}
