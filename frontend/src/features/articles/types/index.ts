export interface TopicSuggestionRequest {
  title: string;
}

export interface TopicSuggestionResponse {
  topics: string[];
}

export interface ScrapeUrlRequest {
  url: string;
}

export interface ScrapeUrlResponse {
  title: string;
  url: string;
}

export interface GenerateOutlineRequest {
  title: string;
  keywords: string[];
  referenceUrls: string[];
}

export interface OutlineItem {
  id: number;
  text: string;
  included: boolean;
}

export interface ReferenceLink {
  id: number;
  title: string;
  url: string;
  included: boolean;
}

export interface GenerateOutlineResponse {
  outline: OutlineItem[];
  suggestedLinks: ReferenceLink[];
}

export interface GenerateArticleRequest {
  title: string;
  keywords: string[];
  tone: number;
  articleLength: string;
  includeLists: boolean;
  includeTables: boolean;
  outline: OutlineItem[];
  references: ReferenceLink[];
}

export interface GenerateArticleResponse {
  markdown: string;
}
