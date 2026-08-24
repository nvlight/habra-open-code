export interface Author {
  id: number;
  login: string;
  name: string;
  avatar: string | null;
  rating: string;
}

export interface HubRef {
  id: number;
  alias: string;
  name: string;
}

export interface CompanyRef {
  id: number;
  slug: string;
  name: string;
}

export interface TagRef {
  id: number;
  name: string;
}

export type PublicationType = 'article' | 'post' | 'news';
export type PublicationStatus = 'draft' | 'sandbox' | 'published';

export interface Publication {
  id: number;
  type: PublicationType;
  type_label: string;
  status: PublicationStatus;
  title: string;
  lead: string;
  cover: string | null;
  difficulty: 'easy' | 'medium' | 'hard' | null;
  difficulty_label: string | null;
  label: string | null;
  label_label: string | null;
  is_translation: boolean;
  original_author?: string | null;
  reading_time: number;
  views_count: number;
  reach?: number;
  rating: number;
  votes_up: number;
  votes_down: number;
  comments_count: number;
  bookmarks_count: number;
  published_at: string | null;
  author: Author;
  company: CompanyRef | null;
  hubs: HubRef[];
  tags: TagRef[];
  body?: string;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface Paginated<T> {
  data: T[];
  links: PaginationLinks;
  meta: PaginationMeta;
}

export interface Comment {
  id: number;
  body: string;
  rating: number;
  parent_id: number | null;
  publication_id: number;
  author: Author;
  replies: Comment[];
  created_at: string;
}

export interface User extends Author {
  email?: string;
  about?: string | null;
  location?: string | null;
  karma?: number;
  publications_count?: number;
  comments_count?: number;
  followers_count?: number;
  following_count?: number;
}

export interface AuthResponse {
  user: User;
  token: string;
}

export interface VoteResult {
  rating: number;
  votes_up?: number;
  votes_down?: number;
}

export interface ValidationErrorResponse {
  message: string;
  errors: Record<string, string[]>;
}

export interface Badge {
  id: number;
  slug: string;
  name: string;
  description: string;
  awarded_at: string | null;
}

export interface UserProfile extends User {
  badges?: Badge[];
  company?: CompanyRef | null;
  created_at?: string;
}

export interface Hub extends HubRef {
  description?: string | null;
  avatar?: string | null;
  rating?: string;
  subscribers_count?: number;
}

export interface IndustryRef {
  id: number;
  name: string;
  slug?: string;
}

export interface Company extends CompanyRef {
  description?: string | null;
  avatar?: string | null;
  website?: string | null;
  rating?: string;
  location?: string | null;
  size?: string | null;
  founded_at?: string | null;
  representative?: Author | null;
  industries?: IndustryRef[];
  subscribers_count?: number;
}

export interface SubscriptionGroups {
  users: Author[];
  hubs: Hub[];
  companies: Company[];
}

export interface PublicationPayload {
  title: string;
  lead: string | null;
  body: string;
  type: PublicationType;
  status: 'draft' | 'sandbox';
  difficulty: 'easy' | 'medium' | 'hard' | null;
  label: string | null;
  is_translation: boolean;
  source_url: string | null;
  original_author: string | null;
  hubs: number[];
  tags: string[];
}
