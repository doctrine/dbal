<?php

/*
 * Generated from grammar/SQLiteParser.g4 by ANTLR 4.11.1
 */

namespace Doctrine\DBAL\Generated;
use Antlr\Antlr4\Runtime\Tree\ParseTreeListener;

/**
 * This interface defines a complete listener for a parse tree produced by
 * {@see SQLiteParser}.
 */
interface SQLiteParserListener extends ParseTreeListener {
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::parse()}.
	 * @param $context The parse tree.
	 */
	public function enterParse(Context\ParseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::parse()}.
	 * @param $context The parse tree.
	 */
	public function exitParse(Context\ParseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::sql_stmt_list()}.
	 * @param $context The parse tree.
	 */
	public function enterSql_stmt_list(Context\Sql_stmt_listContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::sql_stmt_list()}.
	 * @param $context The parse tree.
	 */
	public function exitSql_stmt_list(Context\Sql_stmt_listContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::sql_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterSql_stmt(Context\Sql_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::sql_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitSql_stmt(Context\Sql_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::alter_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterAlter_table_stmt(Context\Alter_table_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::alter_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitAlter_table_stmt(Context\Alter_table_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::analyze_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterAnalyze_stmt(Context\Analyze_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::analyze_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitAnalyze_stmt(Context\Analyze_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::attach_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterAttach_stmt(Context\Attach_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::attach_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitAttach_stmt(Context\Attach_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::begin_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterBegin_stmt(Context\Begin_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::begin_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitBegin_stmt(Context\Begin_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::commit_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCommit_stmt(Context\Commit_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::commit_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCommit_stmt(Context\Commit_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::rollback_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterRollback_stmt(Context\Rollback_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::rollback_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitRollback_stmt(Context\Rollback_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::savepoint_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterSavepoint_stmt(Context\Savepoint_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::savepoint_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitSavepoint_stmt(Context\Savepoint_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::release_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterRelease_stmt(Context\Release_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::release_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitRelease_stmt(Context\Release_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::create_index_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCreate_index_stmt(Context\Create_index_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::create_index_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCreate_index_stmt(Context\Create_index_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::indexed_column()}.
	 * @param $context The parse tree.
	 */
	public function enterIndexed_column(Context\Indexed_columnContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::indexed_column()}.
	 * @param $context The parse tree.
	 */
	public function exitIndexed_column(Context\Indexed_columnContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::create_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCreate_table_stmt(Context\Create_table_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::create_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCreate_table_stmt(Context\Create_table_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::column_def()}.
	 * @param $context The parse tree.
	 */
	public function enterColumn_def(Context\Column_defContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::column_def()}.
	 * @param $context The parse tree.
	 */
	public function exitColumn_def(Context\Column_defContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::type_name()}.
	 * @param $context The parse tree.
	 */
	public function enterType_name(Context\Type_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::type_name()}.
	 * @param $context The parse tree.
	 */
	public function exitType_name(Context\Type_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::column_constraint()}.
	 * @param $context The parse tree.
	 */
	public function enterColumn_constraint(Context\Column_constraintContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::column_constraint()}.
	 * @param $context The parse tree.
	 */
	public function exitColumn_constraint(Context\Column_constraintContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::signed_number()}.
	 * @param $context The parse tree.
	 */
	public function enterSigned_number(Context\Signed_numberContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::signed_number()}.
	 * @param $context The parse tree.
	 */
	public function exitSigned_number(Context\Signed_numberContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::table_constraint()}.
	 * @param $context The parse tree.
	 */
	public function enterTable_constraint(Context\Table_constraintContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::table_constraint()}.
	 * @param $context The parse tree.
	 */
	public function exitTable_constraint(Context\Table_constraintContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::foreign_key_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterForeign_key_clause(Context\Foreign_key_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::foreign_key_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitForeign_key_clause(Context\Foreign_key_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::conflict_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterConflict_clause(Context\Conflict_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::conflict_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitConflict_clause(Context\Conflict_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::create_trigger_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCreate_trigger_stmt(Context\Create_trigger_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::create_trigger_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCreate_trigger_stmt(Context\Create_trigger_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::create_view_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCreate_view_stmt(Context\Create_view_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::create_view_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCreate_view_stmt(Context\Create_view_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::create_virtual_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCreate_virtual_table_stmt(Context\Create_virtual_table_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::create_virtual_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCreate_virtual_table_stmt(Context\Create_virtual_table_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::with_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterWith_clause(Context\With_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::with_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitWith_clause(Context\With_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::cte_table_name()}.
	 * @param $context The parse tree.
	 */
	public function enterCte_table_name(Context\Cte_table_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::cte_table_name()}.
	 * @param $context The parse tree.
	 */
	public function exitCte_table_name(Context\Cte_table_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::recursive_cte()}.
	 * @param $context The parse tree.
	 */
	public function enterRecursive_cte(Context\Recursive_cteContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::recursive_cte()}.
	 * @param $context The parse tree.
	 */
	public function exitRecursive_cte(Context\Recursive_cteContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::common_table_expression()}.
	 * @param $context The parse tree.
	 */
	public function enterCommon_table_expression(Context\Common_table_expressionContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::common_table_expression()}.
	 * @param $context The parse tree.
	 */
	public function exitCommon_table_expression(Context\Common_table_expressionContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::delete_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterDelete_stmt(Context\Delete_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::delete_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitDelete_stmt(Context\Delete_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::delete_stmt_limited()}.
	 * @param $context The parse tree.
	 */
	public function enterDelete_stmt_limited(Context\Delete_stmt_limitedContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::delete_stmt_limited()}.
	 * @param $context The parse tree.
	 */
	public function exitDelete_stmt_limited(Context\Delete_stmt_limitedContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::detach_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterDetach_stmt(Context\Detach_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::detach_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitDetach_stmt(Context\Detach_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::drop_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterDrop_stmt(Context\Drop_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::drop_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitDrop_stmt(Context\Drop_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::expr()}.
	 * @param $context The parse tree.
	 */
	public function enterExpr(Context\ExprContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::expr()}.
	 * @param $context The parse tree.
	 */
	public function exitExpr(Context\ExprContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::raise_function()}.
	 * @param $context The parse tree.
	 */
	public function enterRaise_function(Context\Raise_functionContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::raise_function()}.
	 * @param $context The parse tree.
	 */
	public function exitRaise_function(Context\Raise_functionContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::literal_value()}.
	 * @param $context The parse tree.
	 */
	public function enterLiteral_value(Context\Literal_valueContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::literal_value()}.
	 * @param $context The parse tree.
	 */
	public function exitLiteral_value(Context\Literal_valueContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::insert_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterInsert_stmt(Context\Insert_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::insert_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitInsert_stmt(Context\Insert_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::returning_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterReturning_clause(Context\Returning_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::returning_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitReturning_clause(Context\Returning_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::upsert_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterUpsert_clause(Context\Upsert_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::upsert_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitUpsert_clause(Context\Upsert_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::pragma_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterPragma_stmt(Context\Pragma_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::pragma_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitPragma_stmt(Context\Pragma_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::pragma_value()}.
	 * @param $context The parse tree.
	 */
	public function enterPragma_value(Context\Pragma_valueContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::pragma_value()}.
	 * @param $context The parse tree.
	 */
	public function exitPragma_value(Context\Pragma_valueContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::reindex_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterReindex_stmt(Context\Reindex_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::reindex_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitReindex_stmt(Context\Reindex_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterSelect_stmt(Context\Select_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitSelect_stmt(Context\Select_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::join_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterJoin_clause(Context\Join_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::join_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitJoin_clause(Context\Join_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::select_core()}.
	 * @param $context The parse tree.
	 */
	public function enterSelect_core(Context\Select_coreContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::select_core()}.
	 * @param $context The parse tree.
	 */
	public function exitSelect_core(Context\Select_coreContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::factored_select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterFactored_select_stmt(Context\Factored_select_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::factored_select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitFactored_select_stmt(Context\Factored_select_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::simple_select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterSimple_select_stmt(Context\Simple_select_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::simple_select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitSimple_select_stmt(Context\Simple_select_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::compound_select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCompound_select_stmt(Context\Compound_select_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::compound_select_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCompound_select_stmt(Context\Compound_select_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::table_or_subquery()}.
	 * @param $context The parse tree.
	 */
	public function enterTable_or_subquery(Context\Table_or_subqueryContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::table_or_subquery()}.
	 * @param $context The parse tree.
	 */
	public function exitTable_or_subquery(Context\Table_or_subqueryContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::result_column()}.
	 * @param $context The parse tree.
	 */
	public function enterResult_column(Context\Result_columnContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::result_column()}.
	 * @param $context The parse tree.
	 */
	public function exitResult_column(Context\Result_columnContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::join_operator()}.
	 * @param $context The parse tree.
	 */
	public function enterJoin_operator(Context\Join_operatorContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::join_operator()}.
	 * @param $context The parse tree.
	 */
	public function exitJoin_operator(Context\Join_operatorContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::join_constraint()}.
	 * @param $context The parse tree.
	 */
	public function enterJoin_constraint(Context\Join_constraintContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::join_constraint()}.
	 * @param $context The parse tree.
	 */
	public function exitJoin_constraint(Context\Join_constraintContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::compound_operator()}.
	 * @param $context The parse tree.
	 */
	public function enterCompound_operator(Context\Compound_operatorContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::compound_operator()}.
	 * @param $context The parse tree.
	 */
	public function exitCompound_operator(Context\Compound_operatorContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::update_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterUpdate_stmt(Context\Update_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::update_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitUpdate_stmt(Context\Update_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::column_name_list()}.
	 * @param $context The parse tree.
	 */
	public function enterColumn_name_list(Context\Column_name_listContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::column_name_list()}.
	 * @param $context The parse tree.
	 */
	public function exitColumn_name_list(Context\Column_name_listContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::update_stmt_limited()}.
	 * @param $context The parse tree.
	 */
	public function enterUpdate_stmt_limited(Context\Update_stmt_limitedContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::update_stmt_limited()}.
	 * @param $context The parse tree.
	 */
	public function exitUpdate_stmt_limited(Context\Update_stmt_limitedContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::qualified_table_name()}.
	 * @param $context The parse tree.
	 */
	public function enterQualified_table_name(Context\Qualified_table_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::qualified_table_name()}.
	 * @param $context The parse tree.
	 */
	public function exitQualified_table_name(Context\Qualified_table_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::vacuum_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterVacuum_stmt(Context\Vacuum_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::vacuum_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitVacuum_stmt(Context\Vacuum_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::filter_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterFilter_clause(Context\Filter_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::filter_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitFilter_clause(Context\Filter_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::window_defn()}.
	 * @param $context The parse tree.
	 */
	public function enterWindow_defn(Context\Window_defnContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::window_defn()}.
	 * @param $context The parse tree.
	 */
	public function exitWindow_defn(Context\Window_defnContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::over_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterOver_clause(Context\Over_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::over_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitOver_clause(Context\Over_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::frame_spec()}.
	 * @param $context The parse tree.
	 */
	public function enterFrame_spec(Context\Frame_specContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::frame_spec()}.
	 * @param $context The parse tree.
	 */
	public function exitFrame_spec(Context\Frame_specContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::frame_clause()}.
	 * @param $context The parse tree.
	 */
	public function enterFrame_clause(Context\Frame_clauseContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::frame_clause()}.
	 * @param $context The parse tree.
	 */
	public function exitFrame_clause(Context\Frame_clauseContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::simple_function_invocation()}.
	 * @param $context The parse tree.
	 */
	public function enterSimple_function_invocation(Context\Simple_function_invocationContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::simple_function_invocation()}.
	 * @param $context The parse tree.
	 */
	public function exitSimple_function_invocation(Context\Simple_function_invocationContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::aggregate_function_invocation()}.
	 * @param $context The parse tree.
	 */
	public function enterAggregate_function_invocation(Context\Aggregate_function_invocationContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::aggregate_function_invocation()}.
	 * @param $context The parse tree.
	 */
	public function exitAggregate_function_invocation(Context\Aggregate_function_invocationContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::window_function_invocation()}.
	 * @param $context The parse tree.
	 */
	public function enterWindow_function_invocation(Context\Window_function_invocationContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::window_function_invocation()}.
	 * @param $context The parse tree.
	 */
	public function exitWindow_function_invocation(Context\Window_function_invocationContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::common_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterCommon_table_stmt(Context\Common_table_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::common_table_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitCommon_table_stmt(Context\Common_table_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::order_by_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterOrder_by_stmt(Context\Order_by_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::order_by_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitOrder_by_stmt(Context\Order_by_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::limit_stmt()}.
	 * @param $context The parse tree.
	 */
	public function enterLimit_stmt(Context\Limit_stmtContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::limit_stmt()}.
	 * @param $context The parse tree.
	 */
	public function exitLimit_stmt(Context\Limit_stmtContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::ordering_term()}.
	 * @param $context The parse tree.
	 */
	public function enterOrdering_term(Context\Ordering_termContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::ordering_term()}.
	 * @param $context The parse tree.
	 */
	public function exitOrdering_term(Context\Ordering_termContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::asc_desc()}.
	 * @param $context The parse tree.
	 */
	public function enterAsc_desc(Context\Asc_descContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::asc_desc()}.
	 * @param $context The parse tree.
	 */
	public function exitAsc_desc(Context\Asc_descContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::frame_left()}.
	 * @param $context The parse tree.
	 */
	public function enterFrame_left(Context\Frame_leftContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::frame_left()}.
	 * @param $context The parse tree.
	 */
	public function exitFrame_left(Context\Frame_leftContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::frame_right()}.
	 * @param $context The parse tree.
	 */
	public function enterFrame_right(Context\Frame_rightContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::frame_right()}.
	 * @param $context The parse tree.
	 */
	public function exitFrame_right(Context\Frame_rightContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::frame_single()}.
	 * @param $context The parse tree.
	 */
	public function enterFrame_single(Context\Frame_singleContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::frame_single()}.
	 * @param $context The parse tree.
	 */
	public function exitFrame_single(Context\Frame_singleContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::window_function()}.
	 * @param $context The parse tree.
	 */
	public function enterWindow_function(Context\Window_functionContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::window_function()}.
	 * @param $context The parse tree.
	 */
	public function exitWindow_function(Context\Window_functionContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::offset()}.
	 * @param $context The parse tree.
	 */
	public function enterOffset(Context\OffsetContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::offset()}.
	 * @param $context The parse tree.
	 */
	public function exitOffset(Context\OffsetContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::default_value()}.
	 * @param $context The parse tree.
	 */
	public function enterDefault_value(Context\Default_valueContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::default_value()}.
	 * @param $context The parse tree.
	 */
	public function exitDefault_value(Context\Default_valueContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::partition_by()}.
	 * @param $context The parse tree.
	 */
	public function enterPartition_by(Context\Partition_byContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::partition_by()}.
	 * @param $context The parse tree.
	 */
	public function exitPartition_by(Context\Partition_byContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::order_by_expr()}.
	 * @param $context The parse tree.
	 */
	public function enterOrder_by_expr(Context\Order_by_exprContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::order_by_expr()}.
	 * @param $context The parse tree.
	 */
	public function exitOrder_by_expr(Context\Order_by_exprContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::order_by_expr_asc_desc()}.
	 * @param $context The parse tree.
	 */
	public function enterOrder_by_expr_asc_desc(Context\Order_by_expr_asc_descContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::order_by_expr_asc_desc()}.
	 * @param $context The parse tree.
	 */
	public function exitOrder_by_expr_asc_desc(Context\Order_by_expr_asc_descContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::expr_asc_desc()}.
	 * @param $context The parse tree.
	 */
	public function enterExpr_asc_desc(Context\Expr_asc_descContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::expr_asc_desc()}.
	 * @param $context The parse tree.
	 */
	public function exitExpr_asc_desc(Context\Expr_asc_descContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::initial_select()}.
	 * @param $context The parse tree.
	 */
	public function enterInitial_select(Context\Initial_selectContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::initial_select()}.
	 * @param $context The parse tree.
	 */
	public function exitInitial_select(Context\Initial_selectContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::recursive_select()}.
	 * @param $context The parse tree.
	 */
	public function enterRecursive_select(Context\Recursive_selectContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::recursive_select()}.
	 * @param $context The parse tree.
	 */
	public function exitRecursive_select(Context\Recursive_selectContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::unary_operator()}.
	 * @param $context The parse tree.
	 */
	public function enterUnary_operator(Context\Unary_operatorContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::unary_operator()}.
	 * @param $context The parse tree.
	 */
	public function exitUnary_operator(Context\Unary_operatorContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::error_message()}.
	 * @param $context The parse tree.
	 */
	public function enterError_message(Context\Error_messageContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::error_message()}.
	 * @param $context The parse tree.
	 */
	public function exitError_message(Context\Error_messageContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::module_argument()}.
	 * @param $context The parse tree.
	 */
	public function enterModule_argument(Context\Module_argumentContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::module_argument()}.
	 * @param $context The parse tree.
	 */
	public function exitModule_argument(Context\Module_argumentContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::column_alias()}.
	 * @param $context The parse tree.
	 */
	public function enterColumn_alias(Context\Column_aliasContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::column_alias()}.
	 * @param $context The parse tree.
	 */
	public function exitColumn_alias(Context\Column_aliasContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::keyword()}.
	 * @param $context The parse tree.
	 */
	public function enterKeyword(Context\KeywordContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::keyword()}.
	 * @param $context The parse tree.
	 */
	public function exitKeyword(Context\KeywordContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::name()}.
	 * @param $context The parse tree.
	 */
	public function enterName(Context\NameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::name()}.
	 * @param $context The parse tree.
	 */
	public function exitName(Context\NameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::function_name()}.
	 * @param $context The parse tree.
	 */
	public function enterFunction_name(Context\Function_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::function_name()}.
	 * @param $context The parse tree.
	 */
	public function exitFunction_name(Context\Function_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::schema_name()}.
	 * @param $context The parse tree.
	 */
	public function enterSchema_name(Context\Schema_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::schema_name()}.
	 * @param $context The parse tree.
	 */
	public function exitSchema_name(Context\Schema_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::table_name()}.
	 * @param $context The parse tree.
	 */
	public function enterTable_name(Context\Table_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::table_name()}.
	 * @param $context The parse tree.
	 */
	public function exitTable_name(Context\Table_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::table_or_index_name()}.
	 * @param $context The parse tree.
	 */
	public function enterTable_or_index_name(Context\Table_or_index_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::table_or_index_name()}.
	 * @param $context The parse tree.
	 */
	public function exitTable_or_index_name(Context\Table_or_index_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::column_name()}.
	 * @param $context The parse tree.
	 */
	public function enterColumn_name(Context\Column_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::column_name()}.
	 * @param $context The parse tree.
	 */
	public function exitColumn_name(Context\Column_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::collation_name()}.
	 * @param $context The parse tree.
	 */
	public function enterCollation_name(Context\Collation_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::collation_name()}.
	 * @param $context The parse tree.
	 */
	public function exitCollation_name(Context\Collation_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::foreign_table()}.
	 * @param $context The parse tree.
	 */
	public function enterForeign_table(Context\Foreign_tableContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::foreign_table()}.
	 * @param $context The parse tree.
	 */
	public function exitForeign_table(Context\Foreign_tableContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::index_name()}.
	 * @param $context The parse tree.
	 */
	public function enterIndex_name(Context\Index_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::index_name()}.
	 * @param $context The parse tree.
	 */
	public function exitIndex_name(Context\Index_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::trigger_name()}.
	 * @param $context The parse tree.
	 */
	public function enterTrigger_name(Context\Trigger_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::trigger_name()}.
	 * @param $context The parse tree.
	 */
	public function exitTrigger_name(Context\Trigger_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::view_name()}.
	 * @param $context The parse tree.
	 */
	public function enterView_name(Context\View_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::view_name()}.
	 * @param $context The parse tree.
	 */
	public function exitView_name(Context\View_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::module_name()}.
	 * @param $context The parse tree.
	 */
	public function enterModule_name(Context\Module_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::module_name()}.
	 * @param $context The parse tree.
	 */
	public function exitModule_name(Context\Module_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::pragma_name()}.
	 * @param $context The parse tree.
	 */
	public function enterPragma_name(Context\Pragma_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::pragma_name()}.
	 * @param $context The parse tree.
	 */
	public function exitPragma_name(Context\Pragma_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::savepoint_name()}.
	 * @param $context The parse tree.
	 */
	public function enterSavepoint_name(Context\Savepoint_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::savepoint_name()}.
	 * @param $context The parse tree.
	 */
	public function exitSavepoint_name(Context\Savepoint_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::table_alias()}.
	 * @param $context The parse tree.
	 */
	public function enterTable_alias(Context\Table_aliasContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::table_alias()}.
	 * @param $context The parse tree.
	 */
	public function exitTable_alias(Context\Table_aliasContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::transaction_name()}.
	 * @param $context The parse tree.
	 */
	public function enterTransaction_name(Context\Transaction_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::transaction_name()}.
	 * @param $context The parse tree.
	 */
	public function exitTransaction_name(Context\Transaction_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::window_name()}.
	 * @param $context The parse tree.
	 */
	public function enterWindow_name(Context\Window_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::window_name()}.
	 * @param $context The parse tree.
	 */
	public function exitWindow_name(Context\Window_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::alias()}.
	 * @param $context The parse tree.
	 */
	public function enterAlias(Context\AliasContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::alias()}.
	 * @param $context The parse tree.
	 */
	public function exitAlias(Context\AliasContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::filename()}.
	 * @param $context The parse tree.
	 */
	public function enterFilename(Context\FilenameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::filename()}.
	 * @param $context The parse tree.
	 */
	public function exitFilename(Context\FilenameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::base_window_name()}.
	 * @param $context The parse tree.
	 */
	public function enterBase_window_name(Context\Base_window_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::base_window_name()}.
	 * @param $context The parse tree.
	 */
	public function exitBase_window_name(Context\Base_window_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::simple_func()}.
	 * @param $context The parse tree.
	 */
	public function enterSimple_func(Context\Simple_funcContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::simple_func()}.
	 * @param $context The parse tree.
	 */
	public function exitSimple_func(Context\Simple_funcContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::aggregate_func()}.
	 * @param $context The parse tree.
	 */
	public function enterAggregate_func(Context\Aggregate_funcContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::aggregate_func()}.
	 * @param $context The parse tree.
	 */
	public function exitAggregate_func(Context\Aggregate_funcContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::table_function_name()}.
	 * @param $context The parse tree.
	 */
	public function enterTable_function_name(Context\Table_function_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::table_function_name()}.
	 * @param $context The parse tree.
	 */
	public function exitTable_function_name(Context\Table_function_nameContext $context): void;
	/**
	 * Enter a parse tree produced by {@see SQLiteParser::any_name()}.
	 * @param $context The parse tree.
	 */
	public function enterAny_name(Context\Any_nameContext $context): void;
	/**
	 * Exit a parse tree produced by {@see SQLiteParser::any_name()}.
	 * @param $context The parse tree.
	 */
	public function exitAny_name(Context\Any_nameContext $context): void;
}