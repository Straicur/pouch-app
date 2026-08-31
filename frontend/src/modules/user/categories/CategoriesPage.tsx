import { useTranslation } from "react-i18next";
import { useListCategoriesQuery } from "../../../store/api/categoryApi";
import { CategoryRow } from "./components/CategoryRow";

// Part 7's category-level keys and Part 9's "pobierz całą kategorię" both
// needed *some* place to list categories — categoryApi's own comment already
// flagged "no tree navigation UI yet" as future work this would become.
// Deliberately still flat (no indentation/tree), just enough to name a
// category's parent for context.
export function CategoriesPage() {
  const { t } = useTranslation();
  const { data: categories, isLoading, error } = useListCategoriesQuery();
  const categoriesById = new Map((categories ?? []).map((category) => [category.id, category]));

  return (
    <div className="categories-page">
      <h1>{t("nav.categories")}</h1>

      {isLoading && <p>{t("common.loading")}</p>}
      {undefined !== error && <p className="form-error">{t("categories.fetchError")}</p>}
      {undefined !== categories && 0 === categories.length && <p>{t("categories.empty")}</p>}

      {undefined !== categories && categories.length > 0 && (
        <ul className="category-list">
          {categories.map((category) => (
            <CategoryRow key={category.id} category={category} categoriesById={categoriesById} />
          ))}
        </ul>
      )}
    </div>
  );
}
