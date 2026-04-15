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
  content?: string;
}

export interface ArticleMetadata {
  friendlyUrl: string;
  metaTitle: string;
  metaKeywords: string;
  metaDescription: string;
  shortText: string;
  emailTitle: string;
  emailText: string;
  leads?: string;
}

export interface GenerateOutlineRequest {
  title: string;
  keywords: string[];
  exampleUrls?: { url: string; title: string; content?: string }[];
  authorityUrls?: { url: string; title: string; content?: string }[];
  audience?: string;
  searchIntent?: string;
  additionalContext?: string;
  masterDLeads?: string[];
  tone?: number;
  sectionCount?: number;
  contentMode?: string | null;
  blogId?: number | null;
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
  debug?: {
    timeTakenSeconds: number;
    textModelUsed: string;
  };
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
  exampleUrls?: { url: string; title: string; content?: string }[];
  authorityUrls?: { url: string; title: string; content?: string }[];
  contentMode?: string | null;
  masterDLeads?: string[];
  blogId?: number | null;
}

export interface GenerateArticleResponse {
  markdown: string;
  debug?: {
    timeTakenSeconds: number;
    textModelUsed: string;
    imageModelUsed: string;
    imagesGenerated: number;
    imageSuccess: boolean;
  };
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
  blog_id?: number | null;
  data: {
    markdown: string;
    [key: string]: any;
  };
}

export interface SavedArticle {
  id: string;
  title: string;
  blog_id?: number | null;
  data?: any;
  user_id?: string;
  created_at: string;
  updated_at: string;
}
