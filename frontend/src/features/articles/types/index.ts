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
  audience?: string;
  searchIntent?: string;
  additionalContext?: string;
  masterDLeads?: string[];
}

export interface OutlineItem {
  id: number;
  text: string;
  included: boolean;
  infographic?: boolean;
  budget?: 'short' | 'medium' | 'long';
}

export interface ReferenceLink {
  id: number;
  title: string;
  url: string;
  included: boolean;
}

export interface LeadItem {
  id: number;
  text: string;
  included: boolean;
}

export interface GenerateOutlineResponse {
  outline: OutlineItem[];
  suggestedLinks: ReferenceLink[];
  masterDLeads?: string[];
}

export interface GenerateArticleRequest {
  title: string;
  keywords: string[];
  tone: number;
  audience?: string;
  searchIntent?: string;
  additionalContext?: string;
  includeLists: boolean;
  includeTables: boolean;
  outline: OutlineItem[];
  references: ReferenceLink[];
  masterDLeads?: string[];
}

export interface GenerateArticleResponse {
  markdown: string;
}

export interface RegenerateSectionRequest {
  articleTitle: string;
  sectionHeading: string;
  currentContent: string;
  context?: string;
  guidelines?: string;
}

export interface RegenerateSectionResponse {
  content: string;
}

export interface SaveArticleRequest {
  title: string;
  data: {
    markdown: string;
    [key: string]: any;
  };
}

export interface SavedArticle {
  id: string;
  title: string;
  data?: any;
  user_id?: string;
  created_at: string;
  updated_at: string;
}
