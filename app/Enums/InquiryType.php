<?php

namespace App\Enums;

enum InquiryType: string
{
    case Trading = 'trading';
    case MarketData = 'market_data';
    case TechnicalIssue = 'technical_issue';
    case GeneralQuestion = 'general_question';
}
